<?php

namespace Tests\Unit\Fieldtypes;

use Statamic\Fields\Field;
use Tests\TestCase;

class LocationSelectTest extends TestCase
{
    /**
     * De volgorde is die van de structuurboom
     * (content/trees/collections/locations.yaml), niet alfabetisch: het
     * ontwerp toont Dilbeek, Sint-Pieters-Leeuw, Aartselaar in die volgorde.
     */
    public function test_the_options_are_the_locations_in_tree_order(): void
    {
        $field = new Field('location', ['type' => 'location_select']);

        $this->assertSame([
            'winsol-dilbeek' => 'Winsol Dilbeek',
            'winsol-sint-pieters-leeuw' => 'Winsol Sint-Pieters-Leeuw',
            'winsol-aartselaar' => 'Winsol Aartselaar',
        ], $field->fieldtype()->extraRenderableFieldData()['options']);
    }

    /**
     * Enkelvoudige waarde, dus de regel hoort op de handle zelf — anders dan
     * bij RangeCheckboxes, dat een array is.
     */
    public function test_a_forged_location_value_is_rejected(): void
    {
        $field = new Field('location', ['type' => 'location_select']);

        $this->assertContains(
            'in:winsol-dilbeek,winsol-sint-pieters-leeuw,winsol-aartselaar',
            $field->rules()['location'],
        );
    }

    /**
     * Het veld is optioneel. Zonder `nullable` zou een lege keuze op de
     * `in:`-regel stuklopen.
     */
    public function test_an_empty_choice_stays_allowed(): void
    {
        $field = new Field('location', ['type' => 'location_select']);

        $this->assertContains('nullable', $field->rules()['location']);
    }
}
