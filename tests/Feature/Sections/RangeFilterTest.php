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

        // Doelt op de statische `class`-attribuutwaarde zelf, niet op een
        // substring-scan over de hele tag: `:class`-Alpine-bindings noemen
        // "range-filter__btn--active" ook letterlijk in knoppen die niet
        // actief zijn, dus die tekst alleen bewijst niets over de echte staat.
        $this->assertMatchesRegularExpression(
            '/data-range=""\s+class="range-filter__btn range-filter__btn--active"/',
            $html,
            '"Toon alles" hoort standaard actief te zijn'
        );

        $this->assertMatchesRegularExpression(
            '/data-range="zonwering"\s+class="range-filter__btn"/',
            $html,
            'Zonder ?range hoort de zonwering-knop niet actief te staan'
        );
    }

    public function test_the_active_state_is_also_exposed_server_side_via_aria_current(): void
    {
        $html = $this->render('{{ partial src="rangeFilter" }}');

        // `:aria-current` is Alpine-only; zonder JavaScript en vóór Alpine
        // boot moet het echte attribuut er al staan.
        $this->assertMatchesRegularExpression(
            '/data-range=""\s+class="range-filter__btn range-filter__btn--active"\s+aria-current="page"/',
            $html,
            '"Toon alles" hoort server-side aria-current="page" te dragen'
        );

        // Klasse en aria-current kunnen niet uiteenlopen: precies één pil.
        $this->assertSame(1, substr_count($html, 'aria-current="page"'));
        $this->assertSame(
            substr_count($html, 'class="range-filter__btn range-filter__btn--active"'),
            substr_count($html, 'aria-current="page"'),
            'Actieve klasse en aria-current horen op dezelfde knoppen te staan'
        );
    }

    public function test_every_range_button_links_to_its_own_query_string(): void
    {
        $html = $this->render('{{ partial src="rangeFilter" }}');

        $this->assertStringContainsString('href="?range=zonwering"', $html);
        $this->assertStringContainsString("select('zonwering')", $html);
    }
}
