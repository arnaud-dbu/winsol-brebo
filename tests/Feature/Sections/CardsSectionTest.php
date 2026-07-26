<?php

namespace Tests\Feature\Sections;

class CardsSectionTest extends SectionTestCase
{
    public function test_renders_a_card_per_item_inside_a_breakpoint_slider(): void
    {
        $html = $this->render('{{ partial src="sections/cards" }}', [
            'title' => 'Alle mogelijkheden op een rij',
            'cards' => [
                ['title' => 'Sfeervolle ledverlichting', 'text' => '<p>Dimbare spots.</p>'],
                ['title' => 'Glazen schuifwanden', 'text' => '<p>Volledig op maat.</p>'],
            ],
        ]);

        $this->assertStringContainsString('data-section="cards"', $html);
        $this->assertMatchesRegularExpression('/section-header--centered(\s|")/', $html);
        $this->assertStringNotContainsString('section-header--centered-from-', $html);
        $this->assertStringContainsString('data-slider-from="md"', $html);
        $this->assertSame(2, substr_count($html, 'swiper-slide'));
        $this->assertSame(2, substr_count($html, 'card--horizontal'));
    }
}
