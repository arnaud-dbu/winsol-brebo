<?php

namespace Tests\Feature\Schema;

use App\Schema\LocationsSchema;
use App\Schema\OrganizationSchema;
use Statamic\Facades\Site;
use Tests\Concerns\CreatesTemporaryContent;
use Tests\TestCase;

class LocationsSchemaTest extends TestCase
{
    use CreatesTemporaryContent;

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

    /**
     * Geen showroom heeft een eigen pagina, dus wijst elke `url` naar zijn
     * kaartje op de contactpagina.
     */
    public function test_every_node_links_to_its_card_on_the_contact_page(): void
    {
        $urls = array_column(LocationsSchema::nodes(), 'url', 'name');

        $this->assertStringEndsWith('/contact#winsol-dilbeek', $urls['Winsol Dilbeek']);
        $this->assertStringEndsWith('/contact#winsol-sint-pieters-leeuw', $urls['Winsol Sint-Pieters-Leeuw']);
        $this->assertStringEndsWith('/contact#winsol-aartselaar', $urls['Winsol Aartselaar']);
        $this->assertStringStartsWith('http', $urls['Winsol Dilbeek']);
    }

    /**
     * Een vestiging heeft geen eigen mailadres. Het algemene adres uit de
     * globals is hetzelfde dat de contactpagina onder de kaartjes toont.
     */
    public function test_every_node_carries_the_general_email_address(): void
    {
        foreach (LocationsSchema::nodes() as $node) {
            $this->assertSame('offertes@winsolspl.be', $node['email'] ?? null, "{$node['name']} heeft geen e-mailadres");
        }
    }

    /**
     * Op coördinaten en niet op het adres, om dezelfde reden als de
     * routeknop op de contactpagina: een adres op deze steenwegen landt
     * geregeld aan de overkant.
     */
    public function test_every_node_points_at_its_spot_on_google_maps(): void
    {
        $maps = array_column(LocationsSchema::nodes(), 'hasMap', 'name');

        $this->assertSame('https://www.google.com/maps/search/?api=1&query=50.842047%2C4.237594', $maps['Winsol Dilbeek'] ?? null);
        $this->assertSame('https://www.google.com/maps/search/?api=1&query=51.114612%2C4.370697', $maps['Winsol Aartselaar'] ?? null);
    }

    public function test_a_showroom_without_coordinates_falls_back_to_its_address(): void
    {
        $this->temporaryEntry('locations', 'winsol-zonder-coordinaten', [
            'name' => 'Winsol Zonder Coördinaten',
            'street' => 'Ninoofsesteenweg',
            'number' => '637',
            'postal_code' => '1700',
            'city' => 'Dilbeek',
        ]);

        $node = collect(LocationsSchema::nodes())->firstWhere('name', 'Winsol Zonder Coördinaten');

        $this->assertSame(
            'https://www.google.com/maps/search/?api=1&query=Ninoofsesteenweg+637%2C+1700+Dilbeek',
            $node['hasMap'] ?? null,
        );
    }

    public function test_a_showroom_without_coordinates_or_address_has_no_map(): void
    {
        $this->temporaryEntry('locations', 'winsol-zonder-adres', [
            'name' => 'Winsol Zonder Adres',
        ]);

        $node = collect(LocationsSchema::nodes())->firstWhere('name', 'Winsol Zonder Adres');

        $this->assertNull($node['hasMap']);
    }

    public function test_the_url_follows_the_language_of_the_visitor(): void
    {
        Site::setCurrent('en');

        $node = collect(LocationsSchema::nodes())->firstWhere('name', 'Winsol Dilbeek');

        $this->assertStringEndsWith('/en/contact#winsol-dilbeek', $node['url']);
    }

    /**
     * Het nummer stond tot 07-09-2026 alleen op Organization, omdat twee
     * showrooms dezelfde centrale delen. Jimmy liet de streeknamen weghalen en
     * het nummer onder de vestiging zetten; dan hoort het ook in de
     * LocalBusiness van die vestiging te staan. Organization houdt de
     * ontdubbelde lijst — zie LocationPhonesTest.
     */
    public function test_every_node_carries_the_telephone_of_its_showroom(): void
    {
        foreach (LocationsSchema::nodes() as $node) {
            $this->assertArrayHasKey('telephone', $node, "{$node['name']} heeft geen telefoonnummer");
            $this->assertMatchesRegularExpression('/^\+32 \d/', $node['telephone']);
        }
    }
}
