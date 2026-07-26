<?php

namespace App\Tags;

use Statamic\Facades\Entry;
use Statamic\Tags\Tags;

class ProjectRanges extends Tags
{
    protected static $handle = 'project_ranges';

    /**
     * De ranges waaraan minstens één gepubliceerd project hangt, ontdubbeld en
     * alfabetisch op titel. Voedt het filter op /realisaties, zodat een klik
     * nooit een lege grid oplevert en het filter meegroeit met de content.
     *
     * Alfabetisch, niet in de volgorde van het ontwerp: de ranges-collectie
     * heeft geen handmatig sorteerveld, dus de categorie-volgorde van /aanbod
     * is hier niet reproduceerbaar zonder een extra veld. Zie de openstaande
     * punten in de spec.
     */
    public function index(): array
    {
        return Entry::query()
            ->where('collection', 'projects')
            ->where('published', true)
            ->get()
            ->map(fn ($project) => $project->augmentedValue('range')->value())
            ->filter()
            ->unique(fn ($range) => $range->slug())
            ->sortBy(fn ($range) => $range->get('title'))
            ->map(fn ($range) => [
                'slug' => $range->slug(),
                'title' => $range->get('title'),
            ])
            ->values()
            ->all();
    }
}
