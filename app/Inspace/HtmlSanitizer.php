<?php

namespace App\Inspace;

use DOMElement;
use DOMNode;

/**
 * Filtert alleen op tagnaam, niet op attributen: wat hier doorheen komt gaat
 * verderop in de keten nog door de ProseMirror-parse, die alleen bekende
 * knopen en attributen overhoudt (`onclick`/`onerror` vallen daar al buiten
 * het schema, `javascript:`/`data:`-hrefs op `LinkMark::allowedProtocols`).
 * Gebruik je deze klasse ooit buiten die keten, dan vervalt die aanname en
 * moet attribuutfiltering alsnog worden toegevoegd.
 */
class HtmlSanitizer
{
    /**
     * Tags waarvan ook de inhoud weg moet. Bij de rest wordt alleen de tag
     * uitgepakt en blijft de tekst staan, zodat een herschreven alinea niet
     * stil leeg raakt.
     */
    private const DROP_CONTENT = ['script', 'style', 'iframe', 'object', 'embed'];

    /** @var list<string> */
    private array $warnings = [];

    /**
     * @param  list<string>  $allowedTags
     */
    public function __construct(private readonly array $allowedTags) {}

    public function clean(string $html): string
    {
        $this->warnings = [];

        if (trim($html) === '') {
            return '';
        }

        $body = HtmlFragment::parse($html);

        $this->walk($body);

        return HtmlFragment::render($body);
    }

    /**
     * @return list<string>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    private function walk(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->nodeName);

            if (in_array($tag, $this->allowedTags, true)) {
                $this->walk($child);

                continue;
            }

            $this->warn($tag);

            if (in_array($tag, self::DROP_CONTENT, true)) {
                $child->parentNode?->removeChild($child);

                continue;
            }

            $this->unwrap($child);
        }
    }

    private function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;

        if ($parent === null) {
            return;
        }

        foreach (iterator_to_array($element->childNodes) as $child) {
            $parent->insertBefore($child, $element);
        }

        $parent->removeChild($element);

        // De uitgepakte kinderen staan nu op het niveau van de ouder en zijn
        // door de lopende iteratie al gepasseerd, dus die moet opnieuw.
        $this->walk($parent);
    }

    private function warn(string $tag): void
    {
        $message = "Tag <{$tag}> is niet toegestaan en is verwijderd.";

        if (! in_array($message, $this->warnings, true)) {
            $this->warnings[] = $message;
        }
    }
}
