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
        $this->assertStringContainsString('lg:items-center lg:text-center', $html);
        $this->assertStringContainsString('data-slider-from="lg"', $html);
        $this->assertSame(2, substr_count($html, 'swiper-slide'));
        $this->assertSame(2, substr_count($html, 'class="card '));
    }

    /**
     * Twee kaarten vullen de rij van twee die card.css standaard zet, dus daar
     * blijft de kaart horizontaal: beeld naast tekst vanaf `@lg` containerbreedte.
     */
    public function test_it_keeps_two_cards_horizontal_in_a_row_of_two(): void
    {
        $html = $this->render('{{ partial src="sections/cards" }}', [
            'title' => 'Alle mogelijkheden op een rij',
            'cards' => [
                ['title' => 'Sfeervolle ledverlichting', 'image' => 'https://example.com/een.jpg'],
                ['title' => 'Glazen schuifwanden', 'image' => 'https://example.com/twee.jpg'],
            ],
        ]);

        $this->assertStringContainsString('data-card-count="2"', $html);
        $this->assertSame(2, substr_count($html, '@lg:flex-row'));
        $this->assertSame(2, substr_count($html, '@lg:w-1/3'));
    }

    /**
     * Bij drie kaarten schakelt card.css naar drie kolommen op `data-card-count`,
     * en gaat het beeld boven de tekst omdat een derde van de container te smal
     * is voor een beeldkolom ernaast.
     */
    public function test_it_stacks_three_cards_for_a_row_of_three(): void
    {
        $html = $this->render('{{ partial src="sections/cards" }}', [
            'title' => 'Het volledige aanbod',
            'cards' => [
                ['title' => 'Dakvensters', 'image' => 'https://example.com/een.jpg'],
                ['title' => 'Buitenzonwering', 'image' => 'https://example.com/twee.jpg'],
                ['title' => 'Rolluiken', 'image' => 'https://example.com/drie.jpg'],
            ],
        ]);

        $this->assertStringContainsString('data-card-count="3"', $html);
        $this->assertSame(3, substr_count($html, 'swiper-slide'));
        $this->assertStringNotContainsString('@lg:flex-row', $html);
        $this->assertStringNotContainsString('@lg:w-1/3', $html);
    }

    /**
     * Een sectie zonder kaarten rendert geen slider, dus er valt ook niets te
     * tellen. Het punt van deze test is dat `count` op een lege set de sectie
     * niet opblaast.
     */
    public function test_it_renders_an_empty_count_without_erroring(): void
    {
        $html = $this->render('{{ partial src="sections/cards" }}', ['title' => 'Nog niets']);

        $this->assertStringContainsString('data-card-count=""', $html);
        $this->assertStringNotContainsString('swiper-slide', $html);
    }
}
