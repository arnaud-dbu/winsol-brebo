<?php

namespace App\Inspace;

use DOMDocument;
use DOMElement;

class HtmlFragment
{
    /**
     * Leest een stuk HTML en geeft de `<body>` terug waar het in hangt.
     *
     * Bewust een volledig document en niet `LIBXML_HTML_NOIMPLIED` met een
     * losse charset-meta vooraan: onder libxml 2.9 (de versie op de
     * productieserver) maakt die vlag van het eerste element de wortel en
     * verdwijnt alles wat erna komt. Achter een meta bleef zo een lege string
     * over, waar Tiptap op crasht. libxml 2.14 en hoger doet dat niet, dus
     * lokaal was daar niets van te zien.
     *
     * Zonder de meta leest DOMDocument de bytes als latin-1 en verminkt hij
     * elk accent.
     *
     * Een `</body>` of `</html>` in de invoer sluit onze eigen wrapper
     * voortijdig: wat erna komt belandt buiten de body, en libxml 2.14 en
     * hoger gooit het na `</html>` helemaal weg. Vandaar dat die twee
     * sluittags er vooraf uit gaan. De openingstags negeert de parser zelf.
     */
    public static function parse(string $html): DOMElement
    {
        $html = (string) preg_replace('~</\s*(?:body|html)\s*>~i', '', $html);

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);

        $document->loadHTML(
            '<html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body>'.$html.'</body></html>',
            LIBXML_HTML_NODEFDTD
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $document->getElementsByTagName('body')->item(0);
    }

    public static function render(DOMElement $body): string
    {
        $out = '';

        foreach ($body->childNodes as $child) {
            $out .= $body->ownerDocument->saveHTML($child);
        }

        return trim($out);
    }
}
