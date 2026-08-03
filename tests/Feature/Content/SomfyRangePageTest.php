<?php

namespace Tests\Feature\Content;

use Tests\TestCase;

class SomfyRangePageTest extends TestCase
{
    public function test_the_page_renders_its_sections_in_the_designed_order(): void
    {
        $html = $this->get('/aanbod/somfy-smart-home')->getContent();

        $positions = [];

        foreach (['text', 'features', 'cards', 'text_image', 'technical_details', 'cta', 'locations'] as $section) {
            $position = strpos($html, 'data-section="'.$section.'"');

            $this->assertNotFalse($position, "Sectie {$section} ontbreekt op de Somfy-pagina");

            $positions[$section] = $position;
        }

        $previous = null;

        foreach ($positions as $section => $position) {
            if ($previous !== null) {
                $this->assertLessThan($position, $previous[1], "Sectie {$section} staat voor {$previous[0]}");
            }

            $previous = [$section, $position];
        }
    }

    public function test_the_four_reasons_carry_an_icon_each(): void
    {
        $html = $this->get('/aanbod/somfy-smart-home')->getContent();

        foreach (['Alles tegelijk', 'Minder energie', 'Van waar je ook bent', 'Alsof je thuis bent'] as $title) {
            $this->assertStringContainsString($title, $html, "Reden {$title} ontbreekt");
        }

        $this->assertSame(4, substr_count($html, 'class="feature-item'));
    }

    public function test_the_three_ways_of_operating_live_in_cards(): void
    {
        $html = $this->get('/aanbod/somfy-smart-home')->getContent();

        foreach (['Smart Control', 'TaHoma-app', 'Spraakassistent'] as $title) {
            $this->assertStringContainsString($title, $html, "Kaart {$title} ontbreekt");
        }
    }
}
