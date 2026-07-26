<?php

namespace Tests\Feature\Sections;

class FeaturesSectionTest extends SectionTestCase
{
    public function test_renders_centered_header_and_one_item_per_feature(): void
    {
        $html = $this->render('{{ partial src="sections/features" }}', [
            'title' => 'Waar we voor staan',
            'overline' => 'Onze aanpak',
            'features' => [
                ['title' => 'Lokaal verankerd', 'text' => 'Drie verkooppunten uit de buurt.'],
                ['title' => 'Eigen plaatsers', 'text' => 'Geen onderaannemers.'],
            ],
        ]);

        $this->assertStringContainsString('data-section="features"', $html);
        $this->assertStringContainsString('section-header--centered-from-lg', $html);
        $this->assertDoesNotMatchRegularExpression('/section-header--centered(\s|")/', $html);
        $this->assertSame(2, substr_count($html, 'feature-item'));
        $this->assertStringContainsString('Lokaal verankerd', $html);
    }
}
