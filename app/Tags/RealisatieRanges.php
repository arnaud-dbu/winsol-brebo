<?php

namespace App\Tags;

use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Tags\Tags;

/**
 * De filterpillen op /realisaties: de gepubliceerde ranges, in hun eigen
 * volgorde, maar alleen die met minstens één realisatie — zodat een klik
 * nooit een lege grid oplevert. Zelfde belofte als de collection-scope op
 * de taxonomy-tag in themeFilter, die hier niet kan: `range` is een
 * entries-veld, geen taxonomie.
 */
class RealisatieRanges extends Tags
{
    /**
     * @return list<array{slug: string, title: mixed}>
     */
    public function index(): array
    {
        $site = Site::current()->handle();

        // `value('range')` erft van de origin: fr/en-localisaties dragen het
        // veld niet zelf en verwijzen dus naar het nl-range-id. Dat id wordt
        // hieronder per site naar de juiste taalversie geresolven.
        $used = Entry::query()
            ->where('collection', 'realisaties')
            ->where('site', $site)
            ->get()
            ->map(fn ($entry) => $entry->value('range'))
            ->filter()
            ->unique();

        return $used
            ->map(fn ($id) => Entry::find($id)?->in($site))
            ->filter()
            ->filter(fn ($entry) => $entry->published())
            ->sortBy(fn ($entry) => $entry->value('order'))
            ->map(fn ($entry) => ['slug' => $entry->slug(), 'title' => $entry->value('title')])
            ->values()
            ->all();
    }
}
