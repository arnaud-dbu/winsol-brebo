<?php

namespace Tests\Feature\Content;

use Tests\TestCase;

class RangeOverviewPageTest extends TestCase
{
    public function test_the_page_renders_with_its_header_and_divider(): void
    {
        $response = $this->get('/aanbod');

        $response->assertOk();
        $response->assertSee('Ons aanbod', false);
        $response->assertSee('page-header__divider', false);
    }

    public function test_it_lists_the_three_categories_in_their_designed_order(): void
    {
        $html = $this->get('/aanbod')->getContent();

        $voorJeWoning = strpos($html, 'Voor je woning');
        $rondomJeWoning = strpos($html, 'Rondom je woning');
        $slimEnComfort = strpos($html, 'Slim &amp; comfort');

        $this->assertNotFalse($voorJeWoning, 'Categorie "Voor je woning" ontbreekt');
        $this->assertNotFalse($rondomJeWoning, 'Categorie "Rondom je woning" ontbreekt');
        $this->assertNotFalse($slimEnComfort, 'Categorie "Slim & comfort" ontbreekt');

        $this->assertLessThan($rondomJeWoning, $voorJeWoning, '"Voor je woning" hoort eerst te staan');
        $this->assertLessThan($slimEnComfort, $rondomJeWoning, '"Rondom je woning" hoort tweede te staan');
    }

    public function test_it_renders_every_range_as_a_card_without_a_slider(): void
    {
        $html = $this->get('/aanbod')->getContent();

        $this->assertSame(9, substr_count($html, 'range-card'), 'Er horen negen range-kaarten te staan');
        $this->assertStringNotContainsString('data-slider', $html);
        $this->assertStringNotContainsString('swiper-slide', $html);

        $this->assertStringContainsString('href="/aanbod/rolluiken"', $html);
        $this->assertStringContainsString('href="/aanbod/somfy-smart-home"', $html);
    }

    public function test_it_lists_the_ranges_in_the_designed_order_within_each_category(): void
    {
        $section = $this->rangeOverviewSection();

        preg_match_all('~href="/aanbod/([a-z0-9-]+)"~', $section, $matches);

        $this->assertSame([
            // Voor je woning
            'ramen-en-deuren',
            'stalen-binnendeuren',
            'velux',
            'airco',
            // Rondom je woning
            'rolluiken',
            'zonwering',
            'pergolas',
            'garagepoorten',
            // Slim & comfort
            'somfy-smart-home',
        ], $matches[1], 'De ranges staan niet in de Figma-volgorde binnen hun categorie');
    }

    public function test_the_page_builder_renders_below_the_categories(): void
    {
        $html = $this->get('/aanbod')->getContent();

        $this->assertStringContainsString('data-section="cta"', $html);
        $this->assertStringContainsString('Niet zeker welke oplossing past?', $html);

        $this->assertLessThan(
            strpos($html, 'data-section="cta"'),
            strpos($html, 'data-section="range-overview"'),
            'De page builder hoort onder de categorieën te staan'
        );
    }

    /**
     * Alleen de range-overview-sectie, zodat links elders op de pagina (navigatie,
     * footer) de volgorde-assertie niet vervuilen.
     */
    private function rangeOverviewSection(): string
    {
        $html = $this->get('/aanbod')->getContent();

        $start = strpos($html, 'data-section="range-overview"');
        $this->assertNotFalse($start, 'De range-overview-sectie ontbreekt');

        $end = strpos($html, '</section>', $start);
        $this->assertNotFalse($end, 'De range-overview-sectie wordt niet afgesloten');

        return substr($html, $start, $end - $start);
    }
}
