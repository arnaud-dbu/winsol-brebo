<?php

namespace Tests\Feature\Sections;

class RangeFilterTest extends SectionTestCase
{
    public function test_renders_show_all_first_followed_by_every_used_range(): void
    {
        $html = $this->render('{{ partial src="rangeFilter" }}');

        $this->assertStringContainsString('class="range-filter"', $html);
        $this->assertStringContainsString('Toon alles', $html);

        // Eén knop voor "Toon alles" plus één per gebruikte range.
        $this->assertSame(5, substr_count($html, 'data-range='));

        $this->assertLessThan(
            strpos($html, 'data-range="ramen-en-deuren"'),
            strpos($html, 'data-range=""'),
            '"Toon alles" hoort vooraan te staan'
        );
    }

    public function test_show_all_is_active_when_no_range_is_selected(): void
    {
        $html = $this->render('{{ partial src="rangeFilter" }}');

        $this->assertMatchesRegularExpression(
            '/data-range=""[^>]*range-filter__btn--active/',
            $html,
            '"Toon alles" hoort standaard actief te zijn'
        );

        $this->assertDoesNotMatchRegularExpression(
            '/data-range="zonwering"[^>]*range-filter__btn--active/',
            $html,
            'Zonder ?range hoort geen enkele range-knop actief te staan'
        );
    }

    public function test_every_range_button_links_to_its_own_query_string(): void
    {
        $html = $this->render('{{ partial src="rangeFilter" }}');

        $this->assertStringContainsString('href="?range=zonwering"', $html);
        $this->assertStringContainsString("select('zonwering')", $html);
    }
}
