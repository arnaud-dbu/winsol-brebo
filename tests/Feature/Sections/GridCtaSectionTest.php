<?php

namespace Tests\Feature\Sections;

class GridCtaSectionTest extends SectionTestCase
{
    public function test_renders_a_panel_per_grid_item(): void
    {
        $html = $this->render('{{ partial src="sections/gridCta" }}', [
            'grid' => [
                ['title' => 'Wij werken met Winsol', 'text' => 'Belgisch merk met 145 jaar vakmanschap.'],
                ['title' => 'Kom ons team versterken', 'text' => 'Stuur ons gerust je cv.'],
            ],
        ]);

        $this->assertStringContainsString('data-section="grid_cta"', $html);
        $this->assertSame(2, substr_count($html, 'grid-cta__panel'));
        $this->assertStringContainsString('Kom ons team versterken', $html);
    }
}
