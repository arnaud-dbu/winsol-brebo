<?php

namespace Tests\Feature\Sections;

class LocationsTest extends SectionTestCase
{
    public function test_it_renders_a_card_per_location_linking_to_contact(): void
    {
        $html = $this->render('{{ partial:locations }}');

        $this->assertStringContainsString('data-section="locations"', $html);
        $this->assertSame(3, substr_count($html, 'href="/contact"'));
        $this->assertStringContainsString('Winsol Dilbeek', $html);
        $this->assertStringContainsString('Winsol Sint-Pieters-Leeuw', $html);
        $this->assertStringContainsString('Winsol Aartselaar', $html);
    }

    public function test_it_renders_the_hardcoded_heading(): void
    {
        $html = $this->render('{{ partial:locations }}');

        $this->assertStringContainsString('Bezoek ons', $html);
        $this->assertStringContainsString('Liever eerst zien en voelen?', $html);
    }

    public function test_it_composes_the_address_from_the_separate_fields(): void
    {
        $html = $this->render('{{ partial:locations }}');

        $this->assertStringContainsString('Ninoofsesteenweg 637, 1700 Dilbeek', $html);
        $this->assertStringContainsString('Boomsesteenweg 70, 2630 Aartselaar', $html);
    }

    public function test_it_lists_the_locations_in_their_designed_order(): void
    {
        $html = $this->render('{{ partial:locations }}');

        $dilbeek = strpos($html, 'Winsol Dilbeek');
        $leeuw = strpos($html, 'Winsol Sint-Pieters-Leeuw');
        $aartselaar = strpos($html, 'Winsol Aartselaar');

        $this->assertLessThan($leeuw, $dilbeek, 'Dilbeek hoort eerst te staan');
        $this->assertLessThan($aartselaar, $leeuw, 'Sint-Pieters-Leeuw hoort tweede te staan');
    }

    public function test_every_card_carries_its_coordinates_for_the_map(): void
    {
        $html = $this->render('{{ partial:locations }}');

        $this->assertSame(3, substr_count($html, 'data-location-lat='));
        $this->assertSame(3, substr_count($html, 'data-location-lng='));
        $this->assertStringContainsString('data-location-lat="50.842047"', $html);
        $this->assertStringContainsString('data-location-lng="4.237594"', $html);
    }

    public function test_a_location_without_coordinates_still_gets_a_card(): void
    {
        // De collectie-entries zijn de fixtures van de andere tests, dus deze
        // failure mode wordt op de losse kaart-partial getest in plaats van er
        // een vierde neplocatie voor in de content te zetten.
        $html = $this->render('{{ partial:locationCard }}', [
            'name' => 'Winsol Zonder Punt',
            'street' => 'Teststraat',
            'number' => '1',
            'postal_code' => '9000',
            'city' => 'Gent',
        ]);

        $this->assertStringContainsString('href="/contact"', $html);
        $this->assertStringContainsString('Teststraat 1, 9000 Gent', $html);
        $this->assertStringNotContainsString('data-location-lat', $html);
        $this->assertStringNotContainsString('data-location-lng', $html);
    }

    public function test_the_map_follows_the_cards_in_the_dom_and_is_hidden_from_assistive_tech(): void
    {
        $html = $this->render('{{ partial:locations }}');

        // Onder lg stapelt het grid in DOM-volgorde, dus dit is wat de gekozen
        // mobiele volgorde (kaartjes boven, kaart eronder) vastpint.
        $this->assertLessThan(
            strpos($html, 'data-locations-map'),
            strpos($html, 'Winsol Aartselaar'),
            'De kaart hoort onder de kaartjes te staan'
        );

        $this->assertStringContainsString('data-locations-map aria-hidden="true"', $html);
    }

    public function test_it_ships_the_pin_svg_once_for_the_map_to_clone(): void
    {
        $html = $this->render('{{ partial:locations }}');

        $this->assertSame(1, substr_count($html, 'data-map-pin'));
        $this->assertStringContainsString('M3.65793 3.77467', $html);
    }

    public function test_it_credits_the_tile_providers_outside_the_hidden_map(): void
    {
        $html = $this->render('{{ partial:locations }}');

        $this->assertStringContainsString('openstreetmap.org/copyright', $html);
        $this->assertStringContainsString('carto.com/attributions', $html);

        // Buiten de aria-hidden container: focusbare links binnen aria-hidden
        // zijn onbereikbaar voor screenreaders maar wel bereikbaar met tab.
        $this->assertLessThan(
            strpos($html, 'openstreetmap.org/copyright'),
            strpos($html, 'data-locations-map'),
            'De attributie hoort na (en buiten) de kaartcontainer te staan'
        );
    }

    public function test_it_does_not_inherit_page_fields_into_its_own_heading(): void
    {
        // sectionHeader leest title/text/link uit de cascade. De partial wordt
        // op willekeurige templates geincludeerd, dus een pagina met een eigen
        // `text`- of `link`-veld mag daar niet in lekken.
        $html = $this->render('{{ partial:locations }}', [
            'text' => 'LEKKAGE-TEKST',
            'link' => [['type' => 'url', 'url' => 'example.com', 'label' => 'LEKKAGE-LINK']],
        ]);

        $this->assertStringNotContainsString('LEKKAGE-TEKST', $html);
        $this->assertStringNotContainsString('LEKKAGE-LINK', $html);
    }
}
