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
}
