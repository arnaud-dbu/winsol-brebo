<?php

namespace Tests\Feature\Sections;

class ProductsSectionTest extends SectionTestCase
{
    public function test_renders_a_card_per_product(): void
    {
        $html = $this->render('{{ partial src="sections/products" }}', [
            'title' => 'Zes soorten terrasoverkapping',
            'overline' => 'producten',
            'products' => [
                ['title' => 'Pergola SO!', 'text' => '<p>Draaibare lamellen.</p>'],
                ['title' => 'Pergola CO!', 'text' => '<p>Vast dak.</p>'],
                ['title' => 'Veranda', 'text' => '<p>Volledig gesloten.</p>'],
            ],
        ]);

        $this->assertStringContainsString('data-section="products"', $html);
        $this->assertStringContainsString('section-header--centered-from-lg', $html);
        $this->assertDoesNotMatchRegularExpression('/section-header--centered(\s|")/', $html);
        $this->assertStringContainsString('data-slider-from="md"', $html);
        $this->assertSame(3, substr_count($html, 'card--vertical'));
    }
}
