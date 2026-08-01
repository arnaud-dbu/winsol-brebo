<?php

namespace Tests\Feature\Content;

use Statamic\Facades\Entry;
use Tests\TestCase;

class LocationsContentTest extends TestCase
{
    public function test_every_location_exists_with_its_address_and_coordinates(): void
    {
        $expected = [
            'winsol-dilbeek' => [
                'name' => 'Winsol Dilbeek',
                'street' => 'Ninoofsesteenweg',
                'number' => '637',
                'postal_code' => '1700',
                'city' => 'Dilbeek',
                'latitude' => 50.842047,
                'longitude' => 4.237594,
            ],
            'winsol-sint-pieters-leeuw' => [
                'name' => 'Winsol Sint-Pieters-Leeuw',
                'street' => 'Bergensesteenweg',
                'number' => '488',
                'postal_code' => '1600',
                'city' => 'Sint-Pieters-Leeuw',
                'latitude' => 50.777979,
                'longitude' => 4.269867,
            ],
            'winsol-aartselaar' => [
                'name' => 'Winsol Aartselaar',
                // Het ontwerp en de oude seed zeiden Antwerpsesteenweg. Het
                // echte adres staat op winsoldilbeek.be en in OpenStreetMap,
                // dat er zelfs "Winsol" op kent.
                'street' => 'Boomsesteenweg',
                'number' => '70',
                'postal_code' => '2630',
                'city' => 'Aartselaar',
                'latitude' => 51.114612,
                'longitude' => 4.370697,
            ],
        ];

        foreach ($expected as $slug => $fields) {
            $entry = Entry::query()->where('collection', 'locations')->where('slug', $slug)->first();

            $this->assertNotNull($entry, "Locatie {$slug} ontbreekt");

            foreach ($fields as $handle => $value) {
                $this->assertSame($value, $entry->get($handle), "Veld {$handle} van {$slug} klopt niet");
            }

            // Het huisnummer staat als string en niet als getal, zodat een
            // leidende nul niet wegvalt.
            $this->assertIsString($entry->get('number'), "Huisnummer van {$slug} is geen string");
        }
    }

    public function test_the_locations_are_ordered_as_designed(): void
    {
        $slugs = Entry::query()
            ->where('collection', 'locations')
            ->orderBy('order')
            ->get()
            ->map->slug()
            ->all();

        $this->assertSame(
            ['winsol-dilbeek', 'winsol-sint-pieters-leeuw', 'winsol-aartselaar'],
            $slugs,
            'De volgorde uit het design (Dilbeek, Sint-Pieters-Leeuw, Aartselaar) klopt niet'
        );
    }

    public function test_the_blueprint_exposes_both_coordinate_fields_as_optional_floats(): void
    {
        $blueprint = Entry::query()->where('collection', 'locations')->first()->blueprint();

        foreach (['latitude', 'longitude'] as $handle) {
            $field = $blueprint->field($handle);

            $this->assertNotNull($field, "Veld {$handle} ontbreekt in de blueprint");
            $this->assertSame('float', $field->type(), "Veld {$handle} hoort een float te zijn");

            // Niet required: een locatie zonder coordinaten hoort in de lijst te
            // blijven staan, en required zou het opslaan blokkeren op precies het
            // moment dat een redacteur een nieuwe vestiging aanmaakt.
            $this->assertFalse($field->isRequired(), "Veld {$handle} hoort optioneel te zijn");
        }
    }

    public function test_every_location_carries_the_designed_opening_hours(): void
    {
        // Alle drie de vestigingen tonen dezelfde uren. Het zijn aparte entries
        // en geen gedeelde global, zodat één vestiging later afwijkende uren kan
        // krijgen zonder codewijziging.
        //
        // Het ontwerp zette maandag en zondag samen op "Gesloten". Op
        // winsoldilbeek.be is maandag op afspraak, dus die twee zijn gesplitst.
        $expected = [
            ['day' => 'Maandag', 'time' => 'Op afspraak'],
            ['day' => 'Di - Vr', 'time' => '10:30 - 17:30'],
            ['day' => 'Zaterdag', 'time' => '10:00 - 16:00'],
            ['day' => 'Zondag', 'time' => 'Gesloten'],
        ];

        $entries = Entry::query()->where('collection', 'locations')->get();

        $this->assertCount(3, $entries);

        foreach ($entries as $entry) {
            $hours = $entry->get('opening_hours');

            $this->assertIsArray($hours, "Locatie {$entry->slug()} heeft geen openingsuren");
            $this->assertCount(4, $hours, "Locatie {$entry->slug()} hoort vier regels te tonen");

            foreach ($expected as $i => $row) {
                $this->assertSame($row['day'], $hours[$i]['day'], "Dag {$i} van {$entry->slug()} klopt niet");
                $this->assertSame($row['time'], $hours[$i]['time'], "Tijd {$i} van {$entry->slug()} klopt niet");
            }
        }
    }
}
