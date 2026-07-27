<?php

namespace Tests\Feature\Content;

use Tests\TestCase;

class ProjectsOverviewPageTest extends TestCase
{
    public function test_the_page_renders_with_its_header_and_divider(): void
    {
        $response = $this->get('/realisaties');

        $response->assertOk();
        $response->assertSee('Realisaties', false);
        $response->assertSee('border-t border-black/10', false);
    }

    public function test_the_filter_only_offers_ranges_that_have_projects(): void
    {
        $html = $this->get('/realisaties')->getContent();

        $this->assertStringContainsString('Toon alles', $html);

        // De vier ranges waaraan projecten hangen.
        $this->assertStringContainsString('data-range="pergolas"', $html);
        $this->assertStringContainsString('data-range="zonwering"', $html);
        $this->assertStringContainsString('data-range="ramen-en-deuren"', $html);
        $this->assertStringContainsString('data-range="rolluiken"', $html);

        // Ranges zonder projecten horen er niet te staan.
        $this->assertStringNotContainsString('data-range="airco"', $html);
        $this->assertStringNotContainsString('data-range="velux"', $html);
        $this->assertStringNotContainsString('data-range="somfy-smart-home"', $html);
    }

    public function test_it_renders_every_project_as_a_card_without_a_slider(): void
    {
        $html = $this->get('/realisaties')->getContent();

        $this->assertSame(6, substr_count($html, 'project-card '));
        $this->assertStringNotContainsString('data-slider', $html);
        $this->assertStringNotContainsString('swiper-slide', $html);
    }

    public function test_without_a_query_string_nothing_is_hidden(): void
    {
        $html = $this->get('/realisaties')->getContent();

        $this->assertSame(0, substr_count($html, '<li hidden'));
    }

    public function test_a_range_query_string_hides_the_others_without_dropping_them(): void
    {
        $html = $this->get('/realisaties?range=zonwering')->getContent();

        // Alle zes kaarten blijven in de DOM staan — Alpine moet ze terug
        // kunnen tonen zonder nieuwe request.
        $this->assertSame(6, substr_count($html, 'project-card '));

        // Eén project hangt aan `zonwering`, dus vijf staan er verborgen.
        $this->assertSame(5, substr_count($html, '<li hidden'));

        $this->assertStringContainsString('Zip-screens op nieuwbouwwoning', $html);
    }

    public function test_a_range_query_string_marks_that_button_active(): void
    {
        $html = $this->get('/realisaties?range=zonwering')->getContent();

        // Doelt op de statische `class`-attribuutwaarde zelf, niet op een
        // substring-scan over de hele tag: `:class`-Alpine-bindings noemen
        // "range-filter__btn--active" ook letterlijk in knoppen die niet
        // actief zijn, dus die tekst alleen bewijst niets over de echte
        // server-side staat (zie RangeFilterTest).
        $this->assertMatchesRegularExpression(
            '/data-range="zonwering"\s+class="range-filter__btn range-filter__btn--active"/',
            $html,
            'De zonwering-knop hoort actief te staan'
        );
    }

    public function test_the_active_pill_carries_a_server_rendered_aria_current(): void
    {
        // Zonder JavaScript en vóór Alpine boot is `aria-current` de enige
        // programmatische actieve staat, dus die moet uit de server komen.
        $html = $this->get('/realisaties?range=zonwering')->getContent();

        $this->assertMatchesRegularExpression(
            '/data-range="zonwering"\s+class="range-filter__btn range-filter__btn--active"\s+aria-current="page"/',
            $html,
            'De actieve knop hoort server-side aria-current="page" te dragen'
        );

        // Precies één pil is actief, en "Toon alles" is het niet.
        $this->assertSame(1, substr_count($html, 'aria-current="page"'));
        $this->assertDoesNotMatchRegularExpression(
            '/data-range=""\s+class="[^"]*"\s+aria-current=/',
            $html,
            '"Toon alles" hoort niet actief te zijn bij ?range=zonwering'
        );
    }

    public function test_show_all_carries_the_aria_current_when_no_range_is_selected(): void
    {
        $html = $this->get('/realisaties')->getContent();

        $this->assertMatchesRegularExpression(
            '/data-range=""\s+class="range-filter__btn range-filter__btn--active"\s+aria-current="page"/',
            $html,
            '"Toon alles" hoort standaard aria-current="page" te dragen'
        );

        $this->assertSame(1, substr_count($html, 'aria-current="page"'));
    }

    public function test_it_wires_up_the_alpine_filter(): void
    {
        $html = $this->get('/realisaties')->getContent();

        // De "geen animatie"-eis geldt voor het filter/de grid die deze
        // template zelf bouwt, niet voor de rest van het document: de
        // site-brede cookie-consent- en navigatie-partials leven buiten dit
        // section-element en horen niet mee te tellen.
        $start = strpos($html, 'data-section="projects-overview"');
        $end = strpos($html, '</section>', $start);
        $section = substr($html, $start, $end - $start);

        $this->assertStringContainsString('x-data="projectFilter(', $section);
        $this->assertStringContainsString(':hidden="!matches(', $section);
        $this->assertStringNotContainsString('x-transition', $section);
    }

    public function test_the_page_builder_renders_below_the_grid(): void
    {
        $html = $this->get('/realisaties')->getContent();

        $this->assertStringContainsString('data-section="cta"', $html);
        $this->assertStringContainsString('Geïnspireerd geraakt?', $html);

        $this->assertLessThan(
            strpos($html, 'data-section="cta"'),
            strpos($html, 'data-section="projects-overview"'),
            'De page builder hoort onder de grid te staan'
        );
    }
}
