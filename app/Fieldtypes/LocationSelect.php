<?php

namespace App\Fieldtypes;

use Statamic\Facades\Entry;
use Statamic\Fieldtypes\Select;

/**
 * Select waarvan de opties uit de `locations`-collectie komen. Zelfde
 * constructie en zelfde reden als App\Fieldtypes\RangeCheckboxes; zie de
 * toelichting daar over waarom één overschreven `getOptions()` volstaat.
 *
 * Anders dan bij RangeCheckboxes staat de `in:`-regel hier op de handle
 * zelf: dit is één waarde, geen array. `Statamic\Fields\Field::rules()`
 * voegt zelf `nullable` toe zolang het veld niet verplicht is.
 */
class LocationSelect extends Select
{
    protected function getOptions(): array
    {
        return $this->locations()
            ->map(fn ($entry) => ['value' => $entry->slug(), 'label' => $entry->get('name')])
            ->values()
            ->all();
    }

    public function rules(): array
    {
        return ['in:'.$this->locations()->map->slug()->implode(',')];
    }

    /**
     * `locations` is een gestructureerde collectie; `orderBy('order')` volgt
     * daar de boom uit content/trees/collections/locations.yaml.
     */
    private function locations()
    {
        return Entry::query()
            ->where('collection', 'locations')
            ->orderBy('order')
            ->get();
    }
}
