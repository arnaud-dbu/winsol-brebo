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
        $this->assertStringContainsString('overline--inverse-until-lg', $html);

        // The all-widths inverse modifier must NOT be used here — desktop is
        // dark-on-accent, not light-on-dark, so the unconditional variant
        // would be wrong at `lg` and up.
        $this->assertStringNotContainsString('section-header--inverse ', $html);
        $this->assertStringNotContainsString('section-header--inverse"', $html);
        $this->assertStringNotContainsString('overline--inverse ', $html);
        $this->assertStringNotContainsString('overline--inverse"', $html);
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
