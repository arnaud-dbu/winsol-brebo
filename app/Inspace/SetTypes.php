<?php

namespace App\Inspace;

use Statamic\Fields\Field;

class SetTypes
{
    /**
     * De set-handles van een Bard-veld. Bard nest ze onder een groep
     * (`sets.<groep>.sets.<handle>`), ook wanneer er maar een groep is.
     *
     * @return list<string>
     */
    public function of(Field $field): array
    {
        $groups = $field->get('sets', []);
        $handles = [];

        foreach ($groups as $group) {
            foreach (array_keys($group['sets'] ?? []) as $handle) {
                $handles[] = (string) $handle;
            }
        }

        return $handles;
    }
}
