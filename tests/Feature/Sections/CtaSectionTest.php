<?php

namespace Tests\Feature\Sections;

class CtaSectionTest extends SectionTestCase
{
    private array $context = [
        'overline' => 'Over ons',
        'title' => 'Lokale verkooppunten, eigen vakmensen',
    ];

    /**
     * De foto is full bleed (`{{ img fill="true" }}` zet hem absoluut over de
     * hele sectie), maar het tekstpaneel zit in `.container` zodat het de
     * sitemarges volgt en niet tegen de schermrand geduwd wordt.
     */
    public function test_text_panel_sits_in_the_container_while_the_image_stays_full_bleed(): void
    {
        $html = $this->render('{{ partial src="sections/cta" }}', $this->context);

        $this->assertStringContainsString('data-section="cta"', $html);
        $this->assertMatchesRegularExpression('/class="[^"]*\bcontainer\b[^"]*"/', $html);
    }

    /**
     * Twee panelen die met een hidden-klasse wisselen, in plaats van één
     * paneel met responsieve sectionHeader-modifiers. Elke header kent
     * daardoor maar één toestand.
     */
    public function test_renders_a_mobile_and_a_desktop_panel_that_swap_at_lg(): void
    {
        $html = $this->render('{{ partial src="sections/cta" }}', $this->context);

        // Twee headers, elk in een eigen paneel. De exacte tint, blur en
        // maxbreedte zijn design-tuning en staan hier bewust niet vast; wat
        // vastligt is dat er precies één paneel per breedte zichtbaar is.
        $this->assertSame(2, substr_count($html, 'class="section-header'));

        // Mobiel paneel: zichtbaar tot `lg`, glasmorf op de foto — dus blur,
        // en witte tekst die de header eronder erft.
        $this->assertMatchesRegularExpression('/class="[^"]*\bbackdrop-blur-\w+\b[^"]*"/', $html);
        $this->assertMatchesRegularExpression('/class="[^"]*\btext-white\b[^"]*\blg:hidden\b/', $html);

        // Desktop paneel: verborgen tot `lg`, daarna een accent kaart.
        $this->assertMatchesRegularExpression('/class="\bhidden\b[^"]*\bbg-accent\b[^"]*\blg:block\b/', $html);
    }

    /**
     * De header erft z'n tekstkleur van het paneel en zet er zelf geen. Alleen
     * de overline-streep heeft nog een vlag nodig (`accent_bg`), want die
     * `::after` erft niet. De responsieve varianten (`inverse_until` /
     * `accent_bg_from`) bestaan niet meer.
     */
    public function test_only_the_overline_rule_still_needs_a_flag(): void
    {
        $html = $this->render('{{ partial src="sections/cta" }}', $this->context);

        // Eén keer: alleen het accent paneel, niet het glaspaneel.
        $this->assertSame(1, substr_count($html, 'overline--rule-dark'));

        $this->assertStringNotContainsString('section-header--inverse', $html);
        $this->assertStringNotContainsString('overline--rule-dark-from', $html);
    }

    public function test_each_panel_gets_a_button_that_reads_on_its_own_background(): void
    {
        $html = $this->render('{{ partial src="sections/cta" }}', $this->context + [
            'link' => [
                'type' => 'url',
                'url' => 'winsol.be',
                'label' => 'Lees ons verhaal',
            ],
        ]);

        // Accent knop op het donkere glas, donkere knop op het gele accent
        // vlak — omgekeerd zou telkens één van de twee wegvallen.
        $this->assertSame(1, substr_count($html, 'btn btn--primary'));
        $this->assertSame(1, substr_count($html, 'btn btn--secondary'));
    }
}
