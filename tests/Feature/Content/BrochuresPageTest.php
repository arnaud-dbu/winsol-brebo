<?php

namespace Tests\Feature\Content;

use Tests\TestCase;

class BrochuresPageTest extends TestCase
{
    public function test_the_page_lives_at_brochures_and_renders_the_form(): void
    {
        $response = $this->get('/brochures');

        $response->assertOk();
        $response->assertSee('Ontvang onze brochures');
        $response->assertSee('name="brochures[]"', false);
        $response->assertSee('role="combobox"', false);
    }

    /**
     * De brochurekaart op een productpagina linkt hierheen met
     * `?brochure=<pad>`; die pdf hoort dan aangevinkt te staan, en alleen
     * die. Breekt dit, dan begint elke bezoeker vanaf de productpagina
     * opnieuw met een leeg formulier.
     */
    public function test_a_product_link_preselects_exactly_its_brochure(): void
    {
        $html = $this->get('/brochures?brochure=brochures/winsol-brochure-rolluiken-nl.pdf')->content();

        $this->assertSame(1, substr_count($html, ' checked'));
        $this->assertMatchesRegularExpression(
            '/value="brochures\/winsol-brochure-rolluiken-nl\.pdf"\s+checked/',
            $html,
        );
    }
}
