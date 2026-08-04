<?php

namespace Tests\Feature\Sections;

class ThemeFilterTest extends SectionTestCase
{
    public function test_renders_show_all_first_followed_by_every_used_theme(): void
    {
        $html = $this->render('{{ partial src="themeFilter" }}');

        $this->assertStringContainsString('<nav class="theme-filter', $html);
        $this->assertStringContainsString('Toon alles', $html);

        // Eén knop voor "Toon alles" plus één per gebruikt thema. Vijf thema's
        // bestaan er, en aan alle vijf hangt er minstens één artikel.
        $this->assertSame(6, substr_count($html, 'data-theme='));

        $this->assertLessThan(
            strpos($html, 'data-theme="bedrijfsnieuws"'),
            strpos($html, 'data-theme=""'),
            '"Toon alles" hoort vooraan te staan'
        );
    }

    public function test_the_themes_are_sorted_alphabetically_by_title(): void
    {
        $html = $this->render('{{ partial src="themeFilter" }}');

        $order = ['bedrijfsnieuws', 'events', 'producten', 'realisaties', 'showroom'];

        $positions = array_map(
            fn ($slug) => strpos($html, 'data-theme="'.$slug.'"'),
            $order
        );

        $sorted = $positions;
        sort($sorted);

        $this->assertSame($sorted, $positions, 'De thema-pillen horen alfabetisch te staan');
    }

    public function test_show_all_is_active_when_no_theme_is_selected(): void
    {
        $html = $this->render('{{ partial src="themeFilter" }}');

        // Doelt op de statische `class`-attribuutwaarde zelf, niet op een
        // substring-scan over de hele tag: `:class`-Alpine-bindings noemen
        // "btn--secondary" ook letterlijk in knoppen die niet actief zijn,
        // dus die tekst alleen bewijst niets over de echte staat.
        $this->assertMatchesRegularExpression(
            '/data-theme=""\s+class="[^"]*btn--secondary[^"]*"/',
            $html,
            '"Toon alles" hoort standaard actief te zijn'
        );

        $this->assertDoesNotMatchRegularExpression(
            '/data-theme="producten"\s+class="[^"]*btn--secondary[^"]*"/',
            $html,
            'Zonder ?theme hoort de producten-knop niet actief te staan'
        );
    }

    public function test_the_active_state_is_also_exposed_server_side_via_aria_current(): void
    {
        $html = $this->render('{{ partial src="themeFilter" }}');

        // `:aria-current` is Alpine-only; zonder JavaScript en vóór Alpine
        // boot moet het echte attribuut er al staan.
        $this->assertMatchesRegularExpression(
            '/data-theme=""\s+class="[^"]*btn--secondary[^"]*"\s+aria-current="page"/',
            $html,
            '"Toon alles" hoort server-side aria-current="page" te dragen'
        );

        // Klasse en aria-current kunnen niet uiteenlopen: precies één pil.
        $this->assertSame(1, substr_count($html, 'aria-current="page"'));
        $this->assertSame(
            preg_match_all('/\sclass="[^"]*btn--secondary[^"]*"/', $html),
            substr_count($html, 'aria-current="page"'),
            'Actieve klasse en aria-current horen op dezelfde knoppen te staan'
        );
    }

    public function test_every_theme_button_links_to_its_own_query_string(): void
    {
        $html = $this->render('{{ partial src="themeFilter" }}');

        $this->assertStringContainsString('href="?theme=producten"', $html);
        $this->assertStringContainsString("select('producten')", $html);
    }
}
