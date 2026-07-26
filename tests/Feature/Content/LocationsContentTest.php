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
                'postal_code' => '1700',
                'city' => 'Dilbeek',
                'latitude' => 50.8631,
                'longitude' => 4.2564,
            ],
            'winsol-sint-pieters-leeuw' => [
                'name' => 'Winsol Sint-Pieters-Leeuw',
                'street' => 'Bergensesteenweg',
                'postal_code' => '1600',
                'city' => 'Sint-Pieters-Leeuw',
                'latitude' => 50.7789,
                'longitude' => 4.2432,
            ],
            'winsol-aartselaar' => [
                'name' => 'Winsol Aartselaar',
                'street' => 'Antwerpsesteenweg',
                'postal_code' => '2630',
                'city' => 'Aartselaar',
                'latitude' => 51.1342,
                'longitude' => 4.3831,
            ],
        ];

        foreach ($expected as $slug => $fields) {
            $entry = Entry::query()->where('collection', 'locations')->where('slug', $slug)->first();

            $this->assertNotNull($entry, "Locatie {$slug} ontbreekt");

            foreach ($fields as $handle => $value) {
                $this->assertSame($value, $entry->get($handle), "Veld {$handle} van {$slug} klopt niet");
            }

            // Het huisnummer is in het design een placeholder (`000`). Het staat
            // hier als string, niet als getal, zodat een leidende nul later niet
            // wegvalt bij het invullen van het echte nummer.
            $this->assertSame('000', $entry->get('number'), "Huisnummer van {$slug} klopt niet");
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
}
