<?php

namespace App\Tags;

use Statamic\Contracts\Entries\Entry;
use Statamic\Facades\Cascade;
use Statamic\Tags\Tags;

/**
 * De querystring achter een offerteknop: op een productpagina het product.
 *
 *     /aanbod/rolluiken/rolluiken-op-zonne-energie  →  ?product=9c5f52b1-…
 *     elke andere pagina                            →  (leeg)
 *
 * Het id en niet de slug: een slug is alleen uniek binnen zijn range.
 * `/aanbod/zonwering/solarfix` en `/aanbod/ramen-en-deuren/solarfix` zijn twee
 * producten in twee productgroepen.
 *
 * De pagina komt uit de cascade en niet uit de context. In de lus van de
 * quicklinkkaarten is `slug` die van de kaart, niet die van het product.
 * `App\Tags\QuoteProduct` leest de parameter op het formulier weer uit.
 */
class QuoteQuery extends Tags
{
    public function index(): string
    {
        $page = Cascade::get('page');

        if (! $page instanceof Entry || $page->collectionHandle() !== 'products') {
            return '';
        }

        return '?'.http_build_query(['product' => $page->id()]);
    }
}
