<?php

namespace Tests\Feature\Sections;

class SectionHeaderTest extends SectionTestCase
{
    private array $context = [
        'overline' => 'In de kijker',
        'title' => 'Pergola SO!',
        'text' => '<p>De pergola met draaibare lamellen.</p>',
    ];

    public function test_renders_overline_title_and_text(): void
    {
        $html = $this->render('{{ partial:sectionHeader }}', $this->context);

        $this->assertStringContainsString('class="overline"', $html);
        $this->assertStringContainsString('<h2', $html);
        $this->assertStringContainsString('Pergola SO!', $html);
        $this->assertStringContainsString('draaibare lamellen', $html);
    }

    public function test_defaults_to_left_aligned(): void
    {
        $html = $this->render('{{ partial:sectionHeader }}', $this->context);

        $this->assertStringNotContainsString('items-center', $html);
        $this->assertStringNotContainsString('text-center', $html);
    }

    /**
     * `is_centered` kent maar één vorm: links onder `lg`, gecentreerd vanaf
     * `lg`. Er is bewust geen variant die op elke breedte centreert en geen
     * `centered_from`-argument meer.
     */
    public function test_centered_variant_centres_from_lg_up(): void
    {
        $html = $this->render('{{ partial:sectionHeader is_centered="true" }}', $this->context);

        $this->assertStringContainsString('lg:items-center lg:text-center', $html);
    }

    /**
     * Centreren gebeurt via `items-center`, en dat is een flex-only
     * eigenschap. Staat de wrapper op block-layout, dan centreert alleen de
     * tekst nog en blijven de overline-streep en de CTA-knop links plakken.
     * De wrapper moet dus een flexkolom zijn.
     */
    public function test_wrapper_is_a_flex_column_so_centering_can_work(): void
    {
        $html = $this->render('{{ partial:sectionHeader is_centered="true" }}', $this->context);

        $this->assertStringContainsString('flex flex-col', $html);
    }

    /**
     * De partial zet zelf geen tekstkleur — niet met een vlag en niet per
     * breakpoint. Ze erft van het vlak eromheen, precies zoals de overline
     * z'n kleur al kreeg. Een lichte header is dus `text-white` op het
     * omhullende paneel; zie CtaSectionTest.
     */
    public function test_partial_never_sets_its_own_text_colour(): void
    {
        foreach (['', 'is_inverse="true"', 'inverse_until="lg"'] as $args) {
            $html = $this->render("{{ partial:sectionHeader {$args} }}", $this->context);

            $this->assertStringNotContainsString('section-header--inverse', $html);
            $this->assertStringNotContainsString('text-white', $html);
        }
    }

    /**
     * `is_inverse` gaf ook een `inverse`-klasse door aan de knop. Die klasse
     * bestond nergens in resources/css, dus ze is mee verdwenen.
     */
    public function test_button_gets_no_dead_inverse_class(): void
    {
        $html = $this->render('{{ partial:sectionHeader is_inverse="true" }}', $this->context + [
            'link' => ['type' => 'url', 'url' => 'winsol.be', 'label' => 'Lees meer'],
        ]);

        $this->assertStringContainsString('Lees meer', $html);
        $this->assertStringNotContainsString('inverse', $html);
    }

    /**
     * `accent_bg="true"` maakt de overline-streep donker: op een accent vlak
     * zou de default-accentstreep geel op geel staan. Ook hier geen
     * responsieve variant (`accent_bg_from`).
     */
    public function test_accent_bg_darkens_the_overline_rule(): void
    {
        $html = $this->render('{{ partial:sectionHeader accent_bg="true" }}', $this->context);

        $this->assertStringContainsString('overline--rule-dark', $html);

        $withoutFlag = $this->render('{{ partial:sectionHeader }}', $this->context);
        $this->assertStringNotContainsString('overline--rule-dark', $withoutFlag);
    }

    public function test_accent_bg_has_no_responsive_variant(): void
    {
        $html = $this->render('{{ partial:sectionHeader accent_bg_from="lg" }}', $this->context);

        $this->assertStringNotContainsString('overline--rule-dark', $html);
    }

    public function test_heading_tag_is_configurable(): void
    {
        $html = $this->render('{{ partial:sectionHeader tag="h3" }}', $this->context);

        $this->assertStringContainsString('<h3', $html);
        $this->assertStringNotContainsString('<h2', $html);
    }

    public function test_renders_nothing_without_title_or_text(): void
    {
        $this->assertSame('', trim($this->render('{{ partial:sectionHeader }}')));
    }
}
