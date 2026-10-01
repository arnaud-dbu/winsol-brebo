<?php

namespace App\Inspace;

use DOMElement;
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

        $body = HtmlFragment::parse($html);

        /** @var list<DOMElement> $anchors */
        $anchors = iterator_to_array($body->getElementsByTagName('a'));

        foreach ($anchors as $anchor) {
            if (! $anchor->hasAttribute('href')) {
                continue;
            }

            $id = $this->entryId($anchor->getAttribute('href'));

            if ($id !== null) {
                $anchor->setAttribute('href', 'statamic://entry::'.$id);
            }
        }

        return HtmlFragment::render($body);
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
}
