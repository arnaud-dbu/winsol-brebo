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
        $response->assertSee('page-header__divider', false);
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

        $this->assertMatchesRegularExpression(
            '/data-range="zonwering"[^>]*range-filter__btn--active/',
            $html,
            'De zonwering-knop hoort actief te staan'
        );
    }

    public function test_it_wires_up_the_alpine_filter(): void
    {
        $html = $this->get('/realisaties')->getContent();

        $this->assertStringContainsString('x-data="projectFilter(', $html);
        $this->assertStringContainsString(':hidden="!matches(', $html);
        $this->assertStringNotContainsString('x-transition', $html);
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
