<?php

namespace App\Inspace;

use DOMDocument;
use DOMElement;
use DOMNode;
use Statamic\Facades\Asset;

class ImageResolver
{
    /** @var list<string> */
    private array $warnings = [];

    /**
     * Binnenkomend: elke <img src> moet naar een asset in onze container
     * wijzen en wordt herschreven naar asset::<uuid>. Alleen in die vorm
     * haalt Statamic's ImageNode de alt-tekst van het asset op; een kale URL
     * blijft een kale URL en krijgt nooit een alt.
     *
     * Loopt over echte `<img>`-knopen in plaats van met een regex op de ruwe
     * HTML te matchen: dat verdraagt enkele aanhalingstekens, hoofdletters in
     * de tagnaam en een `>` binnen een attribuutwaarde, en het pikt geen
     * `<img>` op die toevallig als tekst in het attribuut van een andere tag
     * staat.
     *
     * De vervangende tag draagt alleen nog src. Andere attributen (width,
     * class, ...) zijn hier geen verlies: Bard\ImageNode::addAttributes()
     * declareert alleen src, dus Tiptap's HTML-parser haalt sowieso niets
     * anders uit de tag dan dat.
     *
     * @throws ExternalImageException
     */
    public function toAssetRefs(string $html): string
    {
        $this->warnings = [];

        if (trim($html) === '') {
            return $html;
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);

        $document->loadHTML(
            '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">'.$html,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $injectedMeta = $document->firstChild;

        /** @var list<DOMElement> $images */
        $images = iterator_to_array($document->getElementsByTagName('img'));

        foreach ($images as $image) {
            $id = $this->assetId($image);

            foreach (iterator_to_array($image->attributes) as $attribute) {
                $image->removeAttribute($attribute->name);
            }

            $image->setAttribute('src', 'asset::'.$id);
        }

        return $this->render($document, $injectedMeta);
    }

    /**
     * @return list<string>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /**
     * @throws ExternalImageException
     */
    private function assetId(DOMElement $image): string
    {
        if ($image->hasAttribute('alt')) {
            $this->warn();
        }

        if (! $image->hasAttribute('src')) {
            throw new ExternalImageException('(zonder src)');
        }

        $src = $image->getAttribute('src');

        if (str_starts_with($src, 'asset::')) {
            return substr($src, strlen('asset::'));
        }

        $asset = Asset::findByUrl($src) ?? Asset::findByUrl((string) parse_url($src, PHP_URL_PATH));

        if ($asset === null) {
            throw new ExternalImageException($src);
        }

        return $asset->id();
    }

    private function warn(): void
    {
        $message = 'Het alt-attribuut op een <img> is genegeerd. Zet de alt-tekst bij de upload via POST /media.';

        if (! in_array($message, $this->warnings, true)) {
            $this->warnings[] = $message;
        }
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
