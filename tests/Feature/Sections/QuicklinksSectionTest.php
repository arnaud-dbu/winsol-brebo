<?php

namespace Tests\Feature\Sections;

class QuicklinksSectionTest extends SectionTestCase
{
    /**
     * De brochurekaart rendert sinds de gated download altijd — met pdf in
     * scope voorgeselecteerd, zonder pdf kaal naar /brochures. Het grid
     * staat daarom vast op drie kolommen; brochures moeten makkelijker
     * vindbaar zijn en vaker aangeboden worden (Jimmy, 26-08).
     */
    public function test_three_columns_and_three_cards_with_a_brochure_in_scope(): void
    {
        $html = $this->render('{{ partial:quicklinks :brochure="brochure" }}', [
            'brochure' => ['url' => '/assets/brochures/pergola-so.pdf', 'path' => 'brochures/pergola-so.pdf'],
        ]);

        $this->assertStringContainsString('lg:grid-cols-3', $html);
        $this->assertSame(3, substr_count($html, '<li'));
        $this->assertSame(3, substr_count($html, 'quicklink-card'));
    }

    public function test_three_columns_and_three_cards_without_a_brochure(): void
    {
        $html = $this->render('{{ partial:quicklinks }}');

        $this->assertStringContainsString('lg:grid-cols-3', $html);
        $this->assertSame(3, substr_count($html, 'quicklink-card'));
        $this->assertStringContainsString('href="/brochures"', $html);
    }

    public function test_a_non_asset_brochure_still_yields_the_plain_form_link(): void
    {
        // Een kale string in plaats van een assetveld: `brochure:path` op een
        // string levert niets op, dus de kaart valt terug op de kale link.
        $html = $this->render('{{ partial:quicklinks :brochure="brochure" }}', [
            'brochure' => 'brochures/weg.pdf',
        ]);

        $this->assertSame(3, substr_count($html, 'quicklink-card'));
        $this->assertStringContainsString('href="/brochures"', $html);
    }
}
