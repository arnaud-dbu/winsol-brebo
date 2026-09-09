<?php

namespace App\Services;

/**
 * Zoekt op waar een adres van de oude site (`winsoldilbeek.be`) op de nieuwe
 * site terechtkomt.
 *
 * De tabel staat in `config/legacy_redirects.php`; hier staan geen adressen.
 * Er is één tabel en één algoritme: een adres dat geen eigen regel heeft klimt
 * segment voor segment omhoog tot het er een vindt. Zo dekken de twaalf
 * productgroepwortels in de tabel ook alle 1512 gemeentepagina's, zonder een
 * tweede laag met patronen.
 *
 * De redenering bij de regels die niet vanzelf spreken staat in
 * `.scratch/redirects/mapping.md`.
 */
class LegacyRedirect
{
    /**
     * De oude site serveerde dezelfde pagina onder verschillende hoofdletters
     * en met en zonder afsluitende slash. Genormaliseerd vallen die varianten
     * op dezelfde regel.
     *
     * De ene leidende slash is er ook om een andere reden: een pad als
     * `//evil.example.com/` komt ongemoeid uit `getPathInfo()`, en
     * `redirect()->to()` leest zo'n string als protocol-relatieve URL en
     * stuurt door naar die host.
     */
    public static function normalise(string $path): string
    {
        $path = strtolower(preg_replace('#/+#', '/', '/'.$path));

        return $path === '/' ? $path : rtrim($path, '/');
    }

    /**
     * Verwacht een genormaliseerd pad. Geeft `null` als het adres nergens op
     * uitkomt.
     */
    public static function destinationFor(string $path, bool $climb = true): ?string
    {
        $paths = config('legacy_redirects.paths');

        if (isset($paths[$path])) {
            return $paths[$path];
        }

        $segments = explode('/', trim($path, '/'));

        if ($brochure = self::brochureFor($segments)) {
            return $brochure;
        }

        if (! $climb) {
            return null;
        }

        $homepages = config('legacy_redirects.homepages');

        // De klim stopt bij het laatste segment, en slaat onderweg de
        // homepages over: alles naar de root sturen leest Google als soft 404.
        while (count($segments) > 1) {
            array_pop($segments);

            $parent = $paths['/'.implode('/', $segments)] ?? null;

            if ($parent !== null && ! in_array($parent, $homepages, true)) {
                return $parent;
            }
        }

        return null;
    }

    /**
     * Een brochurepagina is herkenbaar aan zijn laatste segment en gaat naar
     * de brochurepagina in de taal van het oude pad. Zonder deze regel zou hij
     * naar de productpagina klimmen: het juiste onderwerp, maar niet wat de
     * bezoeker kwam halen.
     *
     * @param  list<string>  $segments
     */
    private static function brochureFor(array $segments): ?string
    {
        if (! in_array(end($segments), config('legacy_redirects.brochure_slugs'), true)) {
            return null;
        }

        return config('legacy_redirects.brochures')['/'.reset($segments)] ?? null;
    }
}
