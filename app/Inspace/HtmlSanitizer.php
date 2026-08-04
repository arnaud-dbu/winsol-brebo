<?php

namespace App\Inspace;

use DOMDocument;
use DOMElement;
use DOMNode;

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

        $this->walk($document);

        $out = '';

        foreach (iterator_to_array($document->childNodes) as $child) {
            if ($child instanceof DOMElement && strtolower($child->nodeName) === 'meta') {
                continue;
            }

            $out .= $document->saveHTML($child);
        }

        return trim($out);
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

            if ($tag === 'meta' || in_array($tag, $this->allowedTags, true)) {
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
