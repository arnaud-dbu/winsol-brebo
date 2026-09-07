<?php

namespace Tests\Feature\Content;

use App\Schema\LocationsSchema;
use Statamic\Facades\Entry;
use Tests\TestCase;

/**
 * De twee telefooncentrales stonden tot 07-09-2026 in de globals, met
 * "Brussel" en "Antwerpen" als label erboven. Jimmy liet dat weghalen: dat
 * zijn geen vestigingen, en klanten uit Dilbeek en Sint-Pieters-Leeuw lazen
 * het als een andere streek dan de hunne. Het nummer hoort nu bij de showroom
 * die opneemt.
 */
class LocationPhonesTest extends TestCase
{
    private const NUMMERS = [
        'winsol-dilbeek' => '+32 2 308 02 26',
        'winsol-sint-pieters-leeuw' => '+32 2 308 02 26',
        'winsol-aartselaar' => '+32 3 880 85 65',
    ];

    public function test_every_showroom_carries_its_own_number(): void
    {
        foreach (self::NUMMERS as $slug => $nummer) {
            $vestiging = Entry::query()
                ->where('collection', 'locations')
                ->where('site', 'nl')
                ->where('slug', $slug)
                ->first();

            $this->assertNotNull($vestiging, "Vestiging {$slug} ontbreekt");
            $this->assertSame($nummer, $vestiging->get('phone'));
        }
    }

    public function test_the_translations_inherit_the_number(): void
    {
        // Een telefoonnummer vertaalt niet, dus het staat alleen op de
        // Nederlandse entry. Zou een vertaling er zelf een dragen, dan zou een
        // gewijzigde centrale er stil naast blijven staan.
        foreach (['fr', 'en'] as $site) {
            $vestiging = Entry::query()
                ->where('collection', 'locations')
                ->where('site', $site)
                ->where('slug', 'winsol-dilbeek')
                ->first();

            $this->assertSame('+32 2 308 02 26', $vestiging->value('phone'));
            $this->assertNull($vestiging->get('phone'));
        }
    }

    public function test_the_local_business_schema_carries_the_number_of_its_showroom(): void
    {
        $nodes = collect(LocationsSchema::nodes())->keyBy('name');

        $this->assertSame('+32 2 308 02 26', $nodes['Winsol Dilbeek']['telephone']);
        $this->assertSame('+32 2 308 02 26', $nodes['Winsol Sint-Pieters-Leeuw']['telephone']);
        $this->assertSame('+32 3 880 85 65', $nodes['Winsol Aartselaar']['telephone']);
    }

    public function test_the_organisation_gets_each_number_once(): void
    {
        // Twee showrooms delen de 02-centrale. Zonder ontdubbelen zou het
        // organisatieknooppunt hetzelfde nummer twee keer opgeven.
        $this->assertSame(['+32 2 308 02 26', '+32 3 880 85 65'], LocationsSchema::phones());
    }

    public function test_the_region_labels_are_gone_from_every_language(): void
    {
        foreach (['nl', 'fr', 'en'] as $taal) {
            $strings = require base_path("lang/{$taal}/site.php");

            $this->assertArrayNotHasKey('contact_region_brussels', $strings);
            $this->assertArrayNotHasKey('contact_region_antwerp', $strings);
        }
    }
}
