<?php

namespace Tests\Feature\Sections;

class GridCtaSectionTest extends SectionTestCase
{
    public function test_renders_a_panel_per_grid_item(): void
    {
        $html = $this->render('{{ partial src="sections/gridCta" }}', [
            'grid' => [
                [
                    'title' => 'Wij werken met Winsol',
                    'text' => 'Belgisch merk met 145 jaar vakmanschap.',
                    'link' => [['type' => 'url', 'url' => 'winsol.eu', 'label' => 'Naar winsol.eu']],
                ],
                [
                    'title' => 'Kom ons team versterken',
                    'text' => 'Stuur ons gerust je cv.',
                    'link' => [['type' => 'url', 'url' => 'winsol.eu/vacatures', 'label' => 'Stuur ons je cv']],
                ],
            ],
        ]);

        $this->assertStringContainsString('data-section="grid_cta"', $html);

        // Het paneel heeft geen eigen `grid-cta__panel`-klasse meer — a257ed5
        // haalde die uit de markup en er staat geen CSS meer tegenover. Wat een
        // paneel tot paneel maakt is nu zijn padding-utility.
        $this->assertSame(2, substr_count($html, 'card-padding-lg'));
        $this->assertStringContainsString('Kom ons team versterken', $html);

        // The first (index 0) item is the light/accent-button panel, the
        // second (index 1) is the accent/dark-button panel — this is the
        // only branching logic in the partial (gridCta.antlers.html:29,39).
        // `btn--accent`/`btn--dark` heten sinds 95da753 `btn--primary`
        // (bg-accent) en `btn--secondary` (bg-black); button.css kent de oude
        // namen niet meer.
        $this->assertSame(1, substr_count($html, 'bg-light'));
        $this->assertSame(1, substr_count($html, 'bg-accent'));
        $this->assertSame(1, substr_count($html, 'btn--primary'));
        $this->assertSame(1, substr_count($html, 'btn--secondary'));
    }
}
