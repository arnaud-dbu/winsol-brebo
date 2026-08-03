<?php

namespace App\Inspace;

use Statamic\Fields\Field;
use Statamic\Fieldtypes\Bard\Augmentor;

class BlockConverter
{
    private Augmentor $augmentor;

    /** @var ?callable(string): string */
    private $transformHtml;

    public function __construct(Field $field, ?callable $transformHtml = null)
    {
        // Uit het echte veld, niet uit een handgemaakte Field: alleen zo
        // dragen de buttons en de sets de configuratie van deze site.
        $this->augmentor = new Augmentor($field->fieldtype());
        $this->transformHtml = $transformHtml;
    }

    /**
     * Splitst de opgeslagen nodes op elke set. Aaneengesloten prozaknopen
     * worden een text-blok; een set wordt een dichte doos die alleen zijn
     * type en row-id draagt.
     *
     * @param  list<array<string, mixed>>  $nodes
     * @return list<array<string, mixed>>
     */
    public function toBlocks(array $nodes): array
    {
        $blocks = [];
        $run = [];

        foreach ($nodes as $node) {
            if (($node['type'] ?? null) !== 'set') {
                $run[] = $node;

                continue;
            }

            if ($run !== []) {
                $blocks[] = ['type' => 'text', 'html' => $this->render($run)];
                $run = [];
            }

            $blocks[] = [
                'type' => (string) ($node['attrs']['values']['type'] ?? 'unknown'),
                'id' => (string) ($node['attrs']['id'] ?? ''),
                'opaque' => true,
            ];
        }

        if ($run !== []) {
            $blocks[] = ['type' => 'text', 'html' => $this->render($run)];
        }

        return $blocks;
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     * @param  list<array<string, mixed>>  $originalNodes
     * @return list<array<string, mixed>>
     *
     * @throws UnknownBlockException
     */
    public function toProsemirror(array $blocks, array $originalNodes): array
    {
        $sets = $this->setsById($originalNodes);
        $unchanged = $this->runsByHtml($originalNodes);
        $out = [];

        foreach ($blocks as $block) {
            if (($block['type'] ?? null) === 'text') {
                $html = (string) ($block['html'] ?? '');

                // De vergelijking gebeurt op de binnenkomende HTML zoals die
                // is, vóór enige transformatie: alleen dan staat hij naast
                // wat een GET op dit moment zou teruggeven.
                $out = array_merge($out, $unchanged[$html] ?? $this->parse($html));

                continue;
            }

            $id = $block['id'] ?? null;

            if ($id === null || ! isset($sets[$id])) {
                throw new UnknownBlockException($id === null ? null : (string) $id);
            }

            $out[] = $sets[$id];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @return array<string, array<string, mixed>>
     */
    private function setsById(array $nodes): array
    {
        $sets = [];

        foreach ($nodes as $node) {
            if (($node['type'] ?? null) === 'set' && isset($node['attrs']['id'])) {
                $sets[(string) $node['attrs']['id']] = $node;
            }
        }

        return $sets;
    }

    /**
     * De HTML van elke prozaloop naar de nodes die hem opleverden, zodat een
     * ongewijzigd blok zijn opslag terugkrijgt in plaats van een geparste
     * kopie met textAlign erop.
     *
     * @param  list<array<string, mixed>>  $nodes
     * @return array<string, list<array<string, mixed>>>
     */
    private function runsByHtml(array $nodes): array
    {
        $runs = [];
        $run = [];

        foreach ($nodes as $node) {
            if (($node['type'] ?? null) !== 'set') {
                $run[] = $node;

                continue;
            }

            if ($run !== []) {
                $runs[$this->render($run)] = $run;
                $run = [];
            }
        }

        if ($run !== []) {
            $runs[$this->render($run)] = $run;
        }

        return $runs;
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
        if ($this->transformHtml !== null) {
            $html = ($this->transformHtml)($html);
        }

        return $this->augmentor->renderHtmlToProsemirror($html)['content'] ?? [];
    }
}
