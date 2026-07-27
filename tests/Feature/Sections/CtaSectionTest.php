<?php

namespace Tests\Feature\Sections;

class CtaSectionTest extends SectionTestCase
{
    public function test_renders_full_bleed_panel_with_responsive_inverse_header(): void
    {
        $html = $this->render('{{ partial src="sections/cta" }}', [
            'overline' => 'Over ons',
            'title' => 'Lokale verkooppunten, eigen vakmensen',
        ]);

        $this->assertStringContainsString('data-section="cta"', $html);
        $this->assertStringNotContainsString('class="container"', $html);

        // Light overline/heading on mobile, dark from `lg` up: one class each,
        // both backed by a real `text-white ... lg:text-black` rule (see
        // resources/css/components/section-header.css and overline.css).
        $this->assertStringContainsString('section-header--inverse-until-lg', $html);

        // Geen aparte `overline--inverse-until-lg`-klasse: de overline-utility
        // (resources/css/base/typography.css) zet zelf geen kleur, ze erft
        // die van de `.section-header`-wrapper. `.section-header--inverse-
        // until-lg` is `@apply text-white lg:text-black` op die wrapper
        // (resources/css/components/section-header.css), dus de overline
        // daarbinnen kleurt vanzelf mee — licht op mobiel, donker vanaf `lg`.

        // The all-widths inverse modifier must NOT be used here — desktop is
        // dark-on-accent, not light-on-dark, so the unconditional variant
        // would be wrong at `lg` and up.
        $this->assertStringNotContainsString('section-header--inverse ', $html);
        $this->assertStringNotContainsString('section-header--inverse"', $html);
        $this->assertStringNotContainsString('overline--inverse ', $html);
        $this->assertStringNotContainsString('overline--inverse"', $html);
    }

    /**
     * Tekstkleur en streepkleur zijn twee losse assen; `cta` is precies het
     * geval waar ze uit elkaar lopen. De tekst erft van de wrapper (zie
     * hierboven), maar het streepje onder de overline is een `::after` met
     * een eigen `background`. Die staat standaard op accent, en het paneel
     * wordt vanaf `lg` zélf accent (`lg:bg-accent`) — geel op geel, dus
     * onzichtbaar. `accent_bg_from="lg"` moet daarom een klasse opleveren
     * die de streep vanaf `lg` donker maakt. Figma 451:2932 ("OVER ONS").
     */
    public function test_overline_rule_turns_dark_on_the_accent_panel(): void
    {
        $html = $this->render('{{ partial src="sections/cta" }}', [
            'overline' => 'Over ons',
            'title' => 'Lokale verkooppunten, eigen vakmensen',
        ]);

        $this->assertStringContainsString('overline--rule-dark-from-lg', $html);
    }

    public function test_panel_switches_from_overlay_to_accent_card_at_lg(): void
    {
        $html = $this->render('{{ partial src="sections/cta" }}', [
            'overline' => 'Over ons',
            'title' => 'Lokale verkooppunten, eigen vakmensen',
        ]);

        // Panel: dark translucent overlay full width on mobile, solid accent
        // and width-constrained from `lg` up (floating card, per Figma).
        $this->assertStringContainsString('bg-black/60', $html);
        $this->assertStringContainsString('lg:bg-accent', $html);
        $this->assertStringContainsString('lg:max-w-[41.6875rem]', $html);
    }

    public function test_button_variant_is_the_responsive_cta_button(): void
    {
        $html = $this->render('{{ partial src="sections/cta" }}', [
            'overline' => 'Over ons',
            'title' => 'Lokale verkooppunten, eigen vakmensen',
            'link' => [
                'type' => 'url',
                'url' => 'winsol.be',
                'label' => 'Lees ons verhaal',
            ],
        ]);

        // Button: accent bg / dark label on mobile, dark bg / white label
        // from `lg` up — single responsive class, see button.css.
        $this->assertStringContainsString('btn--cta', $html);
    }
}
