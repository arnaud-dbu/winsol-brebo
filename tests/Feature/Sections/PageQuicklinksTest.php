<?php

namespace Tests\Feature\Sections;

class PageQuicklinksTest extends SectionTestCase
{
    private function twoQuicklinks(): array
    {
        return [
            [
                'title' => 'Vraag offerte aan',
                'text' => 'Vrijblijvend en op maat van jouw woning.',
                'link_style' => 'primary',
                'link' => [[
                    'type' => 'url',
                    'url' => 'example.com',
                    'label' => 'Vraag offerte aan',
                ]],
            ],
            [
                'title' => 'Een herstelling melden',
                'text' => 'Al klant en werkt er iets niet? Meld het via het herstelformulier.',
                'link_style' => 'outline',
                'link' => [[
                    'type' => 'url',
                    'url' => 'example.com',
                    'label' => 'Naar herstelformulier',
                ]],
            ],
        ];
    }

    public function test_it_renders_a_card_per_row_under_the_hardcoded_title(): void
    {
        $html = $this->render('{{ partial:pageQuicklinks }}', [
            'quicklinks' => $this->twoQuicklinks(),
        ]);

        $this->assertStringContainsString('data-section="page_quicklinks"', $html);
        $this->assertStringContainsString('Zet de volgende stap', $html);
        $this->assertSame(2, substr_count($html, 'quicklink-card'));
        $this->assertStringContainsString('Vraag offerte aan', $html);
        $this->assertStringContainsString('Een herstelling melden', $html);
        $this->assertStringContainsString('Naar herstelformulier', $html);
    }

    public function test_the_first_button_is_filled_and_the_second_is_outlined(): void
    {
        $html = $this->render('{{ partial:pageQuicklinks }}', [
            'quicklinks' => $this->twoQuicklinks(),
        ]);

        // De link_style-mapping is de enige vertakking in de kaart, dus dat is
        // wat vastgepind hoort te worden.
        $this->assertSame(1, substr_count($html, 'btn--primary'));
        $this->assertSame(1, substr_count($html, 'btn--outline'));
    }

    public function test_it_lays_the_cards_out_in_two_columns(): void
    {
        $html = $this->render('{{ partial:pageQuicklinks }}', [
            'quicklinks' => $this->twoQuicklinks(),
        ]);

        // Twee kolommen, waar de collectie-component er drie toont. Het grid
        // zelf komt uit `.quicklink-grid`, dat de overhang-ruimte reserveert.
        $this->assertStringContainsString('quicklink-grid lg:grid-cols-2', $html);
    }

    public function test_it_renders_nothing_without_quicklinks(): void
    {
        // Zodat andere templates de partial gerust mogen includen.
        $html = $this->render('{{ partial:pageQuicklinks }}');

        $this->assertStringNotContainsString('data-section="page_quicklinks"', $html);
        $this->assertStringNotContainsString('Zet de volgende stap', $html);
    }
}
