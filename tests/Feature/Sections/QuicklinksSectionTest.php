<?php

namespace Tests\Feature\Sections;

class QuicklinksSectionTest extends SectionTestCase
{
    public function test_three_columns_when_a_brochure_is_present(): void
    {
        $html = $this->render('{{ partial:quicklinks :brochure="brochure" }}', [
            'brochure' => ['url' => '/assets/brochures/pergola-so.pdf'],
        ]);

        $this->assertStringContainsString('lg:grid-cols-3', $html);
        $this->assertStringNotContainsString('lg:grid-cols-2', $html);
    }

    public function test_two_columns_when_there_is_no_brochure(): void
    {
        $html = $this->render('{{ partial:quicklinks }}');

        $this->assertStringContainsString('lg:grid-cols-2', $html);
        $this->assertStringNotContainsString('lg:grid-cols-3', $html);
    }

    public function test_two_columns_when_the_brochure_is_not_an_asset(): void
    {
        // Een kale string in plaats van een assetveld: quicklinkCard verbergt
        // de brochurekaart al op `brochure:url` (commit 3ba06ed), dus het
        // kolomaantal moet dezelfde toets gebruiken — anders houdt het grid
        // drie kolommen over voor maar twee kaarten.
        $html = $this->render('{{ partial:quicklinks :brochure="brochure" }}', [
            'brochure' => 'brochures/weg.pdf',
        ]);

        $this->assertStringContainsString('lg:grid-cols-2', $html);
        $this->assertStringNotContainsString('lg:grid-cols-3', $html);
        $this->assertSame(2, substr_count($html, 'quicklink-card'));
    }

    /**
     * Twee kaarten is niet hetzelfde als twee gridcellen. Zolang de `<li>` in de
     * lus stond en alleen de kaart erbinnen zich verborg, bleef er een lege
     * `<li>` achter: die telt mee als cel, dus in twee kolommen zakte de derde
     * kaart naar een tweede rij met een gat ernaast. `quicklink-card` tellen zag
     * dat niet, want dat telt precies de kaarten die er wél zijn.
     */
    public function test_a_hidden_brochure_card_leaves_no_empty_grid_cell(): void
    {
        $html = $this->render('{{ partial:quicklinks :brochure="brochure" }}', [
            'brochure' => 'brochures/weg.pdf',
        ]);

        $this->assertSame(2, substr_count($html, '<li'));
        $this->assertSame(2, substr_count($html, '</li>'));
    }

    public function test_all_three_cells_are_there_when_the_brochure_exists(): void
    {
        $html = $this->render('{{ partial:quicklinks :brochure="brochure" }}', [
            'brochure' => ['url' => '/assets/brochures/pergola-so.pdf'],
        ]);

        $this->assertSame(3, substr_count($html, '<li'));
        $this->assertSame(3, substr_count($html, 'quicklink-card'));
    }
}
