<?php

namespace App\Fieldtypes;

use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Fieldtypes\Select;

/**
 * Select waarvan de opties de gepubliceerde ranges zijn. Zelfde constructie en
 * zelfde reden als App\Fieldtypes\LocationSelect; zie de toelichting bij
 * App\Fieldtypes\RangeCheckboxes over waarom één overschreven `getOptions()`
 * volstaat en waarom de slug wordt opgeslagen en niet de entry-id.
 *
 * Een vaste optielijst in het blueprint kan hier niet: die zou op /fr en /en
 * ook in het Nederlands staan.
 */
class RangeSelect extends Select
{
    protected function getOptions(): array
    {
        return $this->ranges()
            ->map(fn ($entry) => ['value' => $entry->slug(), 'label' => $entry->value('title')])
            ->values()
            ->all();
    }

    public function rules(): array
    {
        return ['in:'.$this->ranges()->map->slug()->implode(',')];
    }

    /**
     * Zelfde reden als bij App\Fieldtypes\LocationSelect: `Fieldtype::view()`
     * leidt de viewnaam af van de handle, en zonder
     * `statamic::forms.fields.range_select` valt het veld stil terug op de
     * tekstveld-view — dan staat er een vrij invulveld waar een keuzelijst
     * hoort.
     */
    public function view()
    {
        return 'statamic::forms.fields.select';
    }

    private function ranges()
    {
        return Entry::query()
            ->where('collection', 'ranges')
            ->where('site', Site::current()->handle())
            ->whereStatus('published')
            ->get()
            ->sortBy(fn ($entry) => $entry->value('order'))
            ->values();
    }
}
