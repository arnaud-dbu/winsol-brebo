<?php

namespace App\Inspace;

use Statamic\Facades\Taxonomy;
use Statamic\Fields\Field;

class TermValues
{
    /**
     * @return list<string>
     */
    public function of(Field $field): array
    {
        $slugs = [];

        foreach ($field->get('taxonomies', []) as $handle) {
            $taxonomy = Taxonomy::findByHandle($handle);

            if ($taxonomy === null) {
                continue;
            }

            foreach ($taxonomy->queryTerms()->get() as $term) {
                $slugs[] = $term->slug();
            }
        }

        return array_values(array_unique($slugs));
    }
}
