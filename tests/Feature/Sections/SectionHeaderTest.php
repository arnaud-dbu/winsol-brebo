<?php

namespace Tests\Feature\Sections;

use PHPUnit\Framework\Attributes\DataProvider;

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

    public function test_inverse_variant(): void
    {
        $html = $this->render('{{ partial:sectionHeader is_inverse="true" }}', $this->context);

        $this->assertStringContainsString('section-header--inverse', $html);

        // Geen aparte `overline--inverse`-klasse: de overline-utility zet zelf
        // geen kleur en erft die van de `.section-header`-wrapper (zie
        // resources/css/base/typography.css en section-header.css).
    }

    public static function inverseUntilBreakpointProvider(): array
    {
        return [
            'sm' => ['sm'],
            'md' => ['md'],
            'lg' => ['lg'],
            'xl' => ['xl'],
            '2xl' => ['2xl'],
        ];
    }

    #[DataProvider('inverseUntilBreakpointProvider')]
    public function test_inverse_until_emits_the_literal_breakpoint_class(string $breakpoint): void
    {
        $html = $this->render('{{ partial:sectionHeader :inverse_until="breakpoint" }}', $this->context + ['breakpoint' => $breakpoint]);

        $this->assertStringContainsString("section-header--inverse-until-{$breakpoint}", $html);

        // Geen aparte `overline--inverse-until-{$breakpoint}`-klasse: zie
        // test_inverse_variant hierboven.
    }

    public function test_inverse_until_with_unsupported_value_falls_back_to_dark_at_every_width(): void
    {
        $html = $this->render('{{ partial:sectionHeader inverse_until="not-a-breakpoint" }}', $this->context);

        $this->assertStringNotContainsString('section-header--inverse', $html);
        $this->assertStringNotContainsString('overline--inverse', $html);
    }

    public function test_is_inverse_wins_when_both_args_are_passed(): void
    {
        $html = $this->render('{{ partial:sectionHeader is_inverse="true" inverse_until="lg" }}', $this->context);

        $this->assertStringContainsString('section-header--inverse', $html);
        $this->assertStringNotContainsString('section-header--inverse-until-lg', $html);
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
