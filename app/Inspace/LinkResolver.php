<?php

namespace App\Inspace;

use DOMDocument;
use DOMElement;
use DOMNode;
use Statamic\Facades\Entry;

class LinkResolver
{
    /**
     * Binnenkomend: een href die naar een bestaande entry wijst wordt een
     * statamic://-referentie, zodat de link een slug-wijziging overleeft. De
     * uitgaande richting doet Statamic's eigen Augmentor.
     *
     * Loopt over echte `<a>`-knopen in plaats van met een regex op de ruwe
     * HTML te matchen: dat verdraagt enkele aanhalingstekens, hoofdletters in
     * tag- of attribuutnaam en een `>` binnen de attribuutwaarde, zonder dat
     * elk geval apart moet worden opgevangen.
     */
    public function toStatamic(string $html): string
    {
        if (trim($html) === '') {
            return $html;
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);

        // Zonder de meta-tag leest DOMDocument de bytes als latin-1 en
        // verminkt hij elk accent. De flags houden de wrapper-html en het
        // doctype eruit.
        $document->loadHTML(
            '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">'.$html,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $injectedMeta = $document->firstChild;

        /** @var list<DOMElement> $anchors */
        $anchors = iterator_to_array($document->getElementsByTagName('a'));

        foreach ($anchors as $anchor) {
            if (! $anchor->hasAttribute('href')) {
                continue;
            }

            $id = $this->entryId($anchor->getAttribute('href'));

            if ($id !== null) {
                $anchor->setAttribute('href', 'statamic://entry::'.$id);
            }
        }

        return $this->render($document, $injectedMeta);
    }

    private function entryId(string $href): ?string
    {
        $path = parse_url($href, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return null;
        }

        $host = parse_url($href, PHP_URL_HOST);

        if ($host !== null && $host !== parse_url(config('app.url'), PHP_URL_HOST)) {
            return null;
        }

        return Entry::findByUri('/'.ltrim($path, '/'))?->id();
    }

    private function render(DOMDocument $document, ?DOMNode $injectedMeta): string
    {
        $out = '';

        foreach (iterator_to_array($document->childNodes) as $child) {
            if ($child === $injectedMeta) {
                continue;
            }

            $out .= $document->saveHTML($child);
        }

        return trim($out);
    }
}
