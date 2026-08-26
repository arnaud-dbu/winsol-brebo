<?php

namespace App\Tags;

use Statamic\Facades\Entry;
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
        $used = Entry::query()
            ->where('collection', 'realisaties')
            ->get()
            ->map(fn ($entry) => $entry->get('range'))
            ->filter()
            ->unique()
            ->all();

        return Entry::query()
            ->where('collection', 'ranges')
            ->whereStatus('published')
            ->whereIn('id', $used)
            ->orderBy('order')
            ->get()
            ->map(fn ($entry) => ['slug' => $entry->slug(), 'title' => $entry->get('title')])
            ->values()
            ->all();
    }
}
