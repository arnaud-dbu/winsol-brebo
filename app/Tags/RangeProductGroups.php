<?php

namespace App\Tags;

use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Facades\Term;
use Statamic\Tags\Tags;

/**
 * De sprongbalk onder de hero van een aanbodpagina: de producten van deze
 * range, gebundeld per productgroep (aluminium, pvc, accessoires …) en in de
 * volgorde van het `order`-veld op de term.
 *
 * Producten zonder groep verdwijnen niet maar vallen achteraan onder
 * "Overige": een pas aangemaakt product hoort vindbaar te zijn vóór iemand
 * eraan denkt er een groep op te zetten.
 */
class RangeProductGroups extends Tags
{
    /**
     * @return list<array{group_title: mixed, spread: bool, products: list<array{title: mixed, url: string}>}>
     */
    public function index(): array
    {
        $site = Site::current()->handle();
        $id = $this->params->get('range') ?? $this->context->value('id');

        if (! $rangeId = $this->originId($id)) {
            return [];
        }

        $grouped = Entry::query()
            ->where('collection', 'products')
            ->where('site', $site)
            ->whereStatus('published')
            ->get()
            // `value()` en niet `get()`: fr/en-producten dragen `range` en
            // `product_groups` niet zelf en erven ze van hun origin. Het
            // geërfde range-id is dat van de nl-range, vandaar de vergelijking
            // met het origin-id hierboven.
            ->filter(fn (EntryContract $product) => in_array($rangeId, $this->ids($product->value('range')), true))
            ->sortBy(fn (EntryContract $product) => $product->value('title'))
            ->groupBy(fn (EntryContract $product) => $this->ids($product->value('product_groups'))[0] ?? '');

        // Eén groep zonder term betekent dat op deze range nog geen enkel
        // product een groep draagt. De kop valt dan terug op de naam van de
        // range zelf: zonder kopje worden het losse woorden op de pagina en
        // is de hiërarchie weg. Geen aparte term per range nodig — die zou de
        // rangetitel alleen maar dupliceren, en zodra er wél echte subgroepen
        // toegekend worden verdwijnt deze terugval vanzelf.
        $ongegroepeerd = $grouped->count() === 1 && $grouped->keys()->first() === '';
        $rangeTitle = $ongegroepeerd ? Entry::find($id)?->value('title') : null;

        return $grouped
            ->map(fn ($products, string $slug) => [
                'slug' => $slug,
                'term' => $slug === '' ? null : Term::find("product_groups::{$slug}")?->in($site),
                'products' => $products,
            ])
            ->sortBy(fn (array $group) => $group['term']?->value('order') ?? PHP_INT_MAX)
            ->map(fn (array $group) => [
                // `group_title` en niet `title`: binnen de groepslus zou een lege
                // `title` terugvallen op de paginacascade, en dan staat de
                // naam van de range als groepskop boven de pillen.
                'group_title' => $ongegroepeerd ? $rangeTitle : ($group['term']?->value('title') ?? __('Overige')),
                // Zonder subgroepen is dit de enige kolom; de lijst verdeelt
                // zichzelf dan over de volle breedte in plaats van als smalle
                // sliert onder zijn kop te blijven staan.
                'spread' => $ongegroepeerd,
                'products' => $group['products']
                    ->map(fn (EntryContract $product) => [
                        'title' => $product->value('title'),
                        'url' => $product->url(),
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Het id waarmee localisaties naar deze entry verwijzen: dat van de
     * origin. Op /fr draagt de rangepagina een eigen id, terwijl de producten
     * het nl-id geërfd hebben — zonder deze stap vindt de balk daar niets.
     */
    private function originId(mixed $id): ?string
    {
        if (! is_string($id) || ! $entry = Entry::find($id)) {
            return null;
        }

        return $entry->origin()?->id() ?? $entry->id();
    }

    /**
     * Entries- en terms-velden leveren nu eens een string, dan weer een array
     * of een collectie van objecten, afhankelijk van augmentatie.
     *
     * @return list<string>
     */
    private function ids(mixed $value): array
    {
        if (blank($value)) {
            return [];
        }

        return collect($value)
            ->map(fn ($item) => match (true) {
                is_string($item) => $item,
                $item instanceof EntryContract => $item->id(),
                is_object($item) && method_exists($item, 'slug') => $item->slug(),
                default => null,
            })
            ->filter()
            ->values()
            ->all();
    }
}
