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
    public function test_renders_one_panel_that_swaps_its_look_at_lg(): void
    {
        $html = $this->render('{{ partial src="sections/cta" }}', $this->context);

        // Eén paneel, geen twee. Er stonden vroeger een mobiel en een desktop
        // paneel naast elkaar in de DOM, elk met dezelfde kop erin, waardoor
        // elke cta-titel twee keer in de broncode stond. Voor tekstlezende
        // crawlers en AI-antwoordsystemen leest dat als een herhaalde kop.
        $this->assertSame(1, substr_count($html, 'class="section-header'));
        $this->assertSame(1, substr_count($html, '<h2'));

        // Onder lg: glasmorf op de foto, dus blur en witte tekst.
        $this->assertMatchesRegularExpression('/class="[^"]*\bbackdrop-blur-\w+\b[^"]*"/', $html);
        $this->assertMatchesRegularExpression('/class="[^"]*\btext-white\b[^"]*"/', $html);

        // Vanaf lg: het accent vlak, zonder blur en met donkere tekst.
        $this->assertMatchesRegularExpression('/class="[^"]*\blg:bg-accent\b[^"]*"/', $html);
        $this->assertMatchesRegularExpression('/class="[^"]*\blg:text-black\b[^"]*"/', $html);
        $this->assertMatchesRegularExpression('/class="[^"]*\blg:backdrop-blur-none\b[^"]*"/', $html);

        // En dus geen paneel meer dat op een breakpoint verborgen wordt.
        $this->assertStringNotContainsString('lg:hidden', $html);
    }

    /**
     * De header erft z'n tekstkleur van het paneel en zet er zelf geen. Alleen
     * de overline-streep heeft nog een vlag nodig (`accent_bg`), want die
     * `::after` erft niet. De responsieve varianten (`inverse_until` /
     * `accent_bg_from`) bestaan niet meer.
     */
    public function test_the_overline_rule_switches_in_css_instead_of_via_a_flag(): void
    {
        $html = $this->render('{{ partial src="sections/cta" }}', $this->context);

        // Het streepje onder de overline ging van accent naar zwart via
        // `accent_bg` op het aparte desktoppaneel. Nu er nog één paneel is,
        // gebeurt die omslag in CSS op `.cta__card .overline::after`, dus de
        // vlag hoort hier niet meer te staan.
        $this->assertStringNotContainsString('overline--rule-dark', $html);
        $this->assertStringContainsString('cta__card', $html);

        $this->assertStringNotContainsString('section-header--inverse', $html);
    }

    /**
     * Mobiel vult het paneel de volle breedte, dus de uitlijning speelt pas
     * vanaf `lg`, waar het paneel smaller is dan de foto.
     */
    public function test_the_panel_sits_left_unless_the_alignment_says_right(): void
    {
        $left = $this->render('{{ partial src="sections/cta" }}', $this->context);
        $right = $this->render('{{ partial src="sections/cta" }}', $this->context + ['align' => 'right']);

        $this->assertStringNotContainsString('lg:items-end', $left);
        $this->assertStringContainsString('lg:items-end', $right);
    }

    public function test_the_button_reads_on_both_backgrounds(): void
    {
        $html = $this->render('{{ partial src="sections/cta" }}', $this->context + [
            'link' => [
                'type' => 'url',
                'url' => 'winsol.be',
                'label' => 'Lees ons verhaal',
            ],
        ]);

        // Accent knop op het donkere glas, donkere knop op het gele accent
        // vlak — omgekeerd valt telkens één van de twee weg. Eén knop die op
        // lg van variant wisselt, want `btn--primary` en `btn--secondary` zijn
        // Tailwind-utilities en dragen dus een `lg:`-variant.
        $this->assertSame(1, substr_count($html, 'btn--primary'));
        $this->assertSame(1, substr_count($html, 'lg:btn--secondary'));

        // Op dezelfde knop, niet op twee verschillende.
        $this->assertMatchesRegularExpression(
            '/class="[^"]*\bbtn--primary\b[^"]*\blg:btn--secondary\b[^"]*"/',
            $html
        );
    }

    /**
     * `parallax-frame` op de sectie en `parallax-media` op het beeld zijn een
     * paar (zie base/motion.css): verlies je er één, dan blijft de build
     * groen en de CSS geldig, en wordt de parallax stilletjes een statische
     * crop.
     */
    public function test_the_section_and_its_image_carry_the_parallax_pairing(): void
    {
        $partial = file_get_contents(resource_path('views/partials/sections/cta.antlers.html'));

        $this->assertMatchesRegularExpression(
            '/<section class="[^"]*\bparallax-frame\b[^"]*" data-section="cta"/',
            $partial,
            'De sectie moet parallax-frame dragen.'
        );
        $this->assertMatchesRegularExpression(
            '/\{\{ img [^}]*class="[^"]*\bparallax-media\b[^"]*"/',
            $partial,
            'Het beeld moet parallax-media dragen.'
        );
    }
}
