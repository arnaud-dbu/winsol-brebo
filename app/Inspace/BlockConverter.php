<?php

namespace App\Inspace;

use Statamic\Fields\Field;
use Statamic\Fieldtypes\Bard\Augmentor;

class BlockConverter
{
    private Augmentor $augmentor;

    /** @var list<string> */
    private array $setTypes;

    /** @var ?callable(string): string */
    private $transformHtml;

    public function __construct(Field $field, ?callable $transformHtml = null)
    {
        // Uit het echte veld, niet uit een handgemaakte Field: alleen zo
        // dragen de buttons en de sets de configuratie van deze site.
        $this->augmentor = new Augmentor($field->fieldtype());
        $this->setTypes = (new SetTypes)->of($field);
        $this->transformHtml = $transformHtml;
    }

    /**
     * Splitst de opgeslagen nodes op elke set. Aaneengesloten prozaknopen
     * worden een text-blok; een set wordt een dichte doos die alleen zijn
     * type en row-id draagt.
     *
     * @param  list<array<string, mixed>>  $nodes
     * @return list<array{type: string, html?: string, id?: string, opaque?: bool}>
     *
     * @throws MissingBlockIdException
     */
    public function toBlocks(array $nodes): array
    {
        $blocks = [];

        foreach ($this->segments($nodes) as $segment) {
            if ($segment['kind'] === 'run') {
                $blocks[] = ['type' => 'text', 'html' => $this->render($segment['nodes'])];

                continue;
            }

            $node = $segment['node'];

            $blocks[] = [
                'type' => (string) ($node['attrs']['values']['type'] ?? 'unknown'),
                'id' => (string) $node['attrs']['id'],
                'opaque' => true,
            ];
        }

        return $blocks;
    }

    /**
     * @param  list<array{type: string, html?: string, id?: string, opaque?: bool}>  $blocks
     * @param  list<array<string, mixed>>  $originalNodes
     * @return list<array<string, mixed>>
     *
     * @throws MissingBlockIdException
     * @throws UnknownBlockException
     */
    public function toProsemirror(array $blocks, array $originalNodes): array
    {
        $sets = $this->setsById($originalNodes);
        $candidatesByHtml = $this->runsByHtml($originalNodes);
        $usedSetIds = [];
        $out = [];

        foreach ($blocks as $block) {
            $type = $block['type'] ?? null;

            if ($type === 'text') {
                $html = (string) ($block['html'] ?? '');

                // De vergelijking gebeurt op de binnenkomende HTML zoals die
                // is, vóór enige transformatie: alleen dan staat hij naast
                // wat een GET op dit moment zou teruggeven.
                $out = array_merge($out, $this->reuseOrParse($html, $candidatesByHtml[$html] ?? []));

                continue;
            }

            if (! is_string($type) || ! in_array($type, $this->setTypes, true)) {
                throw new UnknownBlockException(null, is_string($type) ? $type : null);
            }

            $id = $block['id'] ?? null;

            if (! is_string($id) && ! is_int($id)) {
                throw new UnknownBlockException(null);
            }

            $key = (string) $id;

            // Een dubbel gebruikte id zou twee Bard-rijen met dezelfde
            // sleutel opleveren — iets wat de CP zelf nooit maakt.
            if (! isset($sets[$key]) || isset($usedSetIds[$key])) {
                throw new UnknownBlockException($key);
            }

            $usedSetIds[$key] = true;
            $out[] = $sets[$key];
        }

        return $out;
    }

    /**
     * Splitst de opgeslagen nodes op elke set in aaneengesloten prozalopen en
     * losse set-knopen. Gedeeld door toBlocks() en setsById()/runsByHtml() zodat
     * de splitslogica niet dubbel met de hand in sync gehouden hoeft te worden.
     *
     * @param  list<array<string, mixed>>  $nodes
     * @return list<array{kind: string, nodes?: list<array<string, mixed>>, node?: array<string, mixed>}>
     *
     * @throws MissingBlockIdException
     */
    private function segments(array $nodes): array
    {
        $segments = [];
        $run = [];

        foreach ($nodes as $node) {
            if (($node['type'] ?? null) !== 'set') {
                $run[] = $node;

                continue;
            }

            if ($run !== []) {
                $segments[] = ['kind' => 'run', 'nodes' => $run];
                $run = [];
            }

            $id = $node['attrs']['id'] ?? null;

            if (! is_string($id) || $id === '') {
                $blockType = $node['attrs']['values']['type'] ?? null;

                throw new MissingBlockIdException(is_string($blockType) ? $blockType : null);
            }

            $segments[] = ['kind' => 'set', 'node' => $node];
        }

        if ($run !== []) {
            $segments[] = ['kind' => 'run', 'nodes' => $run];
        }

        return $segments;
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @return array<string, array<string, mixed>>
     *
     * @throws MissingBlockIdException
     */
    private function setsById(array $nodes): array
    {
        $sets = [];

        foreach ($this->segments($nodes) as $segment) {
            if ($segment['kind'] === 'set') {
                $sets[(string) $segment['node']['attrs']['id']] = $segment['node'];
            }
        }

        return $sets;
    }

    /**
     * De HTML van elke prozaloop naar de nodes die hem opleverden, zodat een
     * ongewijzigd blok zijn opslag terugkrijgt in plaats van een geparste
     * kopie met textAlign erop. Meerdere runs kunnen naar dezelfde HTML
     * renderen (bv. twee lege alinea's rond een video); daarom houdt dit alle
     * kandidaten per sleutel bij in plaats van er één te laten overschrijven.
     *
     * @param  list<array<string, mixed>>  $nodes
     * @return array<string, list<list<array<string, mixed>>>>
     *
     * @throws MissingBlockIdException
     */
    private function runsByHtml(array $nodes): array
    {
        $runs = [];

        foreach ($this->segments($nodes) as $segment) {
            if ($segment['kind'] === 'run') {
                $runs[$this->render($segment['nodes'])][] = $segment['nodes'];
            }
        }

        return $runs;
    }

    /**
     * Hergebruikt de opgeslagen nodes alleen als elke kandidaat onder deze
     * HTML-sleutel identiek is. Renderen twee verschillende runs toevallig
     * dezelfde HTML (een onrenderbare knoop maakt zo'n run onderscheidbaar
     * van een andere met exact dezelfde zichtbare inhoud), dan is niet meer
     * vast te stellen welke kandidaat bij dit blok hoort — reuse zou dan de
     * verkeerde inhoud kunnen teruggeven, dus valt dit terug op parse().
     *
     * @param  list<list<array<string, mixed>>>  $candidates
     * @return list<array<string, mixed>>
     */
    private function reuseOrParse(string $html, array $candidates): array
    {
        if ($candidates === []) {
            return $this->parse($html);
        }

        $first = json_encode($candidates[0]);

        foreach ($candidates as $candidate) {
            if (json_encode($candidate) !== $first) {
                return $this->parse($html);
            }
        }

        return $candidates[0];
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     */
    private function render(array $nodes): string
    {
        return $this->augmentor->renderProsemirrorToHtml(['type' => 'doc', 'content' => $nodes]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parse(string $html): array
    {
        // Een leeggemaakt text-blok (de tekst tussen twee video's wissen) mag
        // niet naar Tiptap's HTML-parser: die verwacht een documentbody en
        // gooit een TypeError op lege of ontbrekende HTML.
        if (trim($html) === '') {
            return [];
        }

        if ($this->transformHtml !== null) {
            $html = ($this->transformHtml)($html);
        }

        $content = $this->augmentor->renderHtmlToProsemirror($html)['content'] ?? [];

        // Statamic's eigen serialisatie schrijft een set weg als een lege
        // <set>-marker zonder data; komt zo'n marker via client-HTML terug,
        // dan levert parsen een set-knoop zonder attrs op die de pagina
        // stuk rendert. Zo'n knoop hoort hier nooit uit te komen: sets lopen
        // uitsluitend via de opaque doos, nooit via tekst-HTML.
        return array_values(array_filter($content, fn (array $node): bool => ($node['type'] ?? null) !== 'set'));
    }
}
