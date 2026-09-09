<?php

namespace App\Tags;

use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Tags\Tags;

/**
 * Het adres van een vaste pagina in de taal van de bezoeker.
 *
 *     {{ page_url:offerte }}   →  /offerte  ·  /fr/devis  ·  /en/quote
 *
 * De sleutel is altijd de Nederlandse slug: die ligt vast en dient als
 * identificatie, niet als pad.
 *
 * Bestond niet zolang alle talen dezelfde slug deelden — toen volstond
 * `{{ site_prefix }}/offerte`. Sinds de Franse en Engelse pagina's een eigen
 * slug dragen, wijst zo'n hardgecodeerd pad naar een 404 zodra de slug
 * verandert, en dat is precies het soort fout dat pas weken later opvalt.
 */
class PageUrl extends Tags
{
    public function wildcard(string $sleutel): string
    {
        $entry = $this->nederlandseEntry($sleutel);

        if (! $entry) {
            return '';
        }

        $site = $this->params->get('site') ?: Site::current()->handle();
        $vertaald = $entry->in($site) ?? $entry;

        return $this->params->bool('absolute')
            ? $vertaald->absoluteUrl()
            : $vertaald->url();
    }

    private function nederlandseEntry(string $sleutel): ?EntryContract
    {
        return Entry::query()
            ->whereIn('collection', ['pages', 'legal'])
            ->where('site', 'nl')
            ->where('slug', $sleutel)
            ->first();
    }
}
