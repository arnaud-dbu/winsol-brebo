<?php

namespace App\Tags;

use Illuminate\Support\Arr;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Tags\Tags;

/**
 * Het product achter `?product=<id>` op het offerteformulier, in de taal van
 * de bezoeker.
 *
 *     {{ quote_product:id }}     →  c6cad995-…  (Volets solaires)
 *     {{ quote_product:range }}  →  volets-roulants
 *
 * De taalknop neemt de parameter mee, dus de Franse pagina kan het id van een
 * Nederlands product krijgen. Dat loopt via de Nederlandse entry naar de
 * Franse vertaling. Een id dat geen product is geeft een lege string: dan
 * staat er niets aangevinkt en blijft het verborgen veld leeg, want een
 * ongeldige waarde daar geeft een foutmelding die de klant niet kan zien.
 */
class QuoteProduct extends Tags
{
    public function id(): string
    {
        return $this->product()?->id() ?? '';
    }

    /**
     * Het `range`-veld staat alleen op de Nederlandse producten; de vertalingen
     * erven het Nederlandse id. `in()` zet het om naar de range in de taal van
     * de bezoeker, want de pills dragen de slug van hun eigen taal.
     */
    public function range(): string
    {
        $id = Arr::first(Arr::wrap($this->product()?->value('range')));
        $range = $id ? Entry::find($id)?->in(Site::current()->handle()) : null;

        return $range?->published() ? $range->slug() : '';
    }

    private function product(): ?EntryContract
    {
        $id = request()->query('product');
        $entry = is_string($id) && $id !== '' ? Entry::find($id) : null;

        if (! $entry || $entry->collectionHandle() !== 'products') {
            return null;
        }

        $product = $entry->root()->in(Site::current()->handle());

        return $product?->published() ? $product : null;
    }
}
