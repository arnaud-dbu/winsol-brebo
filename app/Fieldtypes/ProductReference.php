<?php

namespace App\Fieldtypes;

use Statamic\Facades\Entry;
use Statamic\Fieldtypes\Hidden;

/**
 * Het product van de pagina waarop de klant op de offerteknop klikte. Een
 * verborgen veld, gevuld door `partials/offerteForm`.
 *
 * De opgeslagen waarde is het id van het product in de taal van de klant. In
 * de mail en de CP-lijst staat de Nederlandse titel: wie de aanvraag bij
 * Brebo opvolgt, kent het product onder die naam.
 *
 * Een verborgen veld is door de klant aan te passen. Alleen het id van een
 * gepubliceerd product komt door, net als de allowlist in `RangeCheckboxes`.
 */
class ProductReference extends Hidden
{
    public function rules(): array
    {
        return ['in:'.$this->products()->map->id()->implode(',')];
    }

    public function augment($value)
    {
        return $this->dutchTitle($value) ?? $value;
    }

    public function preProcessIndex($value)
    {
        return $this->augment($value);
    }

    private function dutchTitle($id): ?string
    {
        return $this->products()->first(fn ($entry) => $entry->id() === $id)?->root()->get('title');
    }

    private function products()
    {
        return Entry::query()->where('collection', 'products')->whereStatus('published')->get();
    }
}
