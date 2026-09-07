<?php

namespace App\Services;

use Statamic\Facades\Entry;

/**
 * Of er een gepubliceerd nieuwsartikel bestaat.
 *
 * De nieuwsoverzichtspagina hoort niet in de navigatie zolang er geen artikel
 * staat: de bezoeker klikt dan naar een lege pagina. Eén keer per request
 * geteld en via de view composer gedeeld met alle views, zodat de drie
 * navigatiepartials (desktop, mobiel, footer) dezelfde bron gebruiken.
 *
 * Een eigen klasse en niet een query in die composer, zodat een test de site
 * nog kan bekijken zoals ze eruitziet zónder nieuws. Sinds er een echt
 * artikel in de content staat — de lopende actie, zie RunningPromotion — is
 * die toestand anders niet meer na te bootsen zonder aan de content te komen.
 */
class PublishedArticles
{
    private ?bool $bestaan = null;

    public function exist(): bool
    {
        return $this->bestaan ??= Entry::query()
            ->where('collection', 'articles')
            ->where('published', true)
            ->count() > 0;
    }
}
