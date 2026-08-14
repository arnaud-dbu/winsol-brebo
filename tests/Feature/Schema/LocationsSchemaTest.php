<?php

namespace Tests\Feature\Schema;

use App\Schema\LocationsSchema;
use App\Schema\OrganizationSchema;
use Tests\TestCase;

class LocationsSchemaTest extends TestCase
{
    public function test_it_builds_one_node_per_showroom(): void
    {
        $this->assertCount(3, LocationsSchema::nodes());
    }

    public function test_dilbeek_carries_its_full_address_and_coordinates(): void
    {
        $node = collect(LocationsSchema::nodes())
            ->firstWhere('name', 'Winsol Dilbeek');

        $this->assertNotNull($node, 'De vestiging Dilbeek hoort in de locations-collectie te staan.');
        $this->assertSame('LocalBusiness', $node['@type']);
        $this->assertSame('Ninoofsesteenweg 637', $node['address']['streetAddress']);
        $this->assertSame('1700', $node['address']['postalCode']);
        $this->assertSame('Dilbeek', $node['address']['addressLocality']);
        $this->assertSame('BE', $node['address']['addressCountry']);
        $this->assertSame(50.842047, $node['geo']['latitude']);
        $this->assertSame(4.237594, $node['geo']['longitude']);
    }

    public function test_every_node_points_at_the_organization(): void
    {
        foreach (LocationsSchema::nodes() as $node) {
            $this->assertSame(OrganizationSchema::id(), $node['parentOrganization']['@id']);
        }
    }

    public function test_opening_hours_are_translated(): void
    {
        $node = collect(LocationsSchema::nodes())->firstWhere('name', 'Winsol Dilbeek');

        $this->assertCount(3, $node['openingHoursSpecification']);
    }

    public function test_each_node_has_a_distinct_id(): void
    {
        $ids = array_column(LocationsSchema::nodes(), '@id');

        $this->assertSame($ids, array_unique($ids));
    }
}
