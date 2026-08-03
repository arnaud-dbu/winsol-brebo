<?php

namespace Tests\Feature\Content;

use Tests\TestCase;

class VeluxRangePageTest extends TestCase
{
    public function test_the_page_renders_its_sections_in_the_designed_order(): void
    {
        $html = $this->get('/aanbod/velux')->getContent();

        $positions = [];

        foreach (['text', 'cards', 'cta', 'locations'] as $section) {
            $position = strpos($html, 'data-section="'.$section.'"');

            $this->assertNotFalse($position, "Sectie {$section} ontbreekt op de Velux-pagina");

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

    /**
     * Het aanbod staat sinds de omvorming tot overzichtspagina in een cards-blok
     * op de range zelf; het products-blok en de drie productpagina's zijn weg.
     */
    public function test_the_offer_lives_in_cards_and_not_in_a_products_block(): void
    {
        $html = $this->get('/aanbod/velux')->getContent();

        $this->assertStringNotContainsString('data-section="products"', $html);

        foreach (['VELUX dakvensters', 'VELUX buitenzonwering', 'VELUX rolluiken'] as $title) {
            $this->assertStringContainsString($title, $html, "Kaart {$title} ontbreekt in het aanbod");
        }
    }

    public function test_the_former_product_pages_no_longer_resolve(): void
    {
        foreach (['velux-dakvensters', 'velux-buitenzonwering', 'velux-rolluiken'] as $slug) {
            $this->get("/aanbod/velux/{$slug}")->assertNotFound();
        }
    }
}
