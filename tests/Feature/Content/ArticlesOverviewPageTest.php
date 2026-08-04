<?php

namespace Tests\Feature\Content;

use Tests\TestCase;

class ArticlesOverviewPageTest extends TestCase
{
    public function test_the_page_renders_with_its_header_and_divider(): void
    {
        $response = $this->get('/nieuws');

        $response->assertOk();
        $response->assertSee('Nieuws', false);
        $response->assertSee('border-t border-black/10', false);
    }

    public function test_the_filter_only_offers_themes_that_have_articles(): void
    {
        $html = $this->get('/nieuws')->getContent();

        $this->assertStringContainsString('Toon alles', $html);

        $this->assertStringContainsString('data-theme="bedrijfsnieuws"', $html);
        $this->assertStringContainsString('data-theme="events"', $html);
        $this->assertStringContainsString('data-theme="producten"', $html);
        $this->assertStringContainsString('data-theme="realisaties"', $html);
        $this->assertStringContainsString('data-theme="showroom"', $html);
    }

    public function test_it_renders_every_article_as_a_card_without_a_slider(): void
    {
        $html = $this->get('/nieuws')->getContent();

        $this->assertSame(8, substr_count($html, 'article-card '));
        $this->assertStringNotContainsString('data-slider', $html);
        $this->assertStringNotContainsString('swiper-slide', $html);
    }

    public function test_without_a_query_string_nothing_is_hidden(): void
    {
        $html = $this->get('/nieuws')->getContent();

        $this->assertSame(0, preg_match_all('/<li\s+hidden/', $html));
    }

    public function test_a_theme_query_string_hides_the_others_without_dropping_them(): void
    {
        $html = $this->get('/nieuws?theme=producten')->getContent();

        // Alle acht kaarten blijven in de DOM staan; Alpine moet ze terug
        // kunnen tonen zonder nieuwe request.
        $this->assertSame(8, substr_count($html, 'article-card '));

        // Twee artikels hangen aan `producten`, dus zes staan er verborgen.
        $this->assertSame(6, preg_match_all('/<li\s+hidden/', $html));

        $this->assertStringContainsString('QUBIC Slide haalt waterdichtheidsklasse 9A', $html);
    }

    public function test_a_theme_query_string_marks_that_button_active(): void
    {
        $html = $this->get('/nieuws?theme=producten')->getContent();

        // Doelt op de statische `class`-attribuutwaarde zelf, niet op een
        // substring-scan over de hele tag: `:class`-Alpine-bindings noemen
        // "btn--secondary" ook letterlijk in knoppen die niet actief zijn.
        $this->assertMatchesRegularExpression(
            '/data-theme="producten"\s+class="[^"]*btn--secondary[^"]*"/',
            $html,
            'De producten-knop hoort actief te staan'
        );
    }

    public function test_the_active_pill_carries_a_server_rendered_aria_current(): void
    {
        // Zonder JavaScript en vóór Alpine boot is `aria-current` de enige
        // programmatische actieve staat, dus die moet uit de server komen.
        $html = $this->get('/nieuws?theme=producten')->getContent();

        $this->assertMatchesRegularExpression(
            '/data-theme="producten"\s+class="[^"]*btn--secondary[^"]*"\s+aria-current="page"/',
            $html,
            'De actieve knop hoort server-side aria-current="page" te dragen'
        );

        $this->assertSame(1, substr_count($html, 'aria-current="page"'));
        $this->assertDoesNotMatchRegularExpression(
            '/data-theme=""\s+class="[^"]*"\s+aria-current=/',
            $html,
            '"Toon alles" hoort niet actief te zijn bij ?theme=producten'
        );
    }

    public function test_show_all_carries_the_aria_current_when_no_theme_is_selected(): void
    {
        $html = $this->get('/nieuws')->getContent();

        $this->assertMatchesRegularExpression(
            '/data-theme=""\s+class="[^"]*btn--secondary[^"]*"\s+aria-current="page"/',
            $html,
            '"Toon alles" hoort standaard aria-current="page" te dragen'
        );

        $this->assertSame(1, substr_count($html, 'aria-current="page"'));
    }

    public function test_it_wires_up_the_alpine_filter(): void
    {
        $html = $this->get('/nieuws')->getContent();

        // De "geen animatie"-eis geldt voor het filter en de grid die deze
        // template zelf bouwt, niet voor de rest van het document: de
        // site-brede cookie-consent- en navigatie-partials leven buiten dit
        // section-element en horen niet mee te tellen.
        $start = strpos($html, 'data-section="articles-overview"');
        $end = strpos($html, '</section>', $start);
        $section = substr($html, $start, $end - $start);

        $this->assertStringContainsString('x-data="articleFilter(', $section);
        $this->assertStringContainsString(':hidden="!matches(', $section);
        $this->assertStringNotContainsString('x-transition', $section);
    }

    public function test_the_newest_article_comes_first(): void
    {
        $html = $this->get('/nieuws')->getContent();

        $this->assertLessThan(
            strpos($html, 'Winsol investeert in een nieuwe lakkerij'),
            strpos($html, 'Onze showroom in Aartselaar is opnieuw open'),
            'De collectie hoort op datum aflopend te sorteren'
        );
    }

    public function test_the_page_builder_renders_below_the_grid(): void
    {
        $html = $this->get('/nieuws')->getContent();

        $this->assertStringContainsString('data-section="cta"', $html);
        $this->assertStringContainsString('Zin om verder te praten?', $html);

        $this->assertLessThan(
            strpos($html, 'data-section="cta"'),
            strpos($html, 'data-section="articles-overview"'),
            'De page builder hoort onder de grid te staan'
        );
    }
}
