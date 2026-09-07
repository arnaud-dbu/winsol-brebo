<?php

namespace Tests\Feature\Sections;

class StickyQuoteTest extends SectionTestCase
{
    public function test_it_offers_a_quote_button_that_only_exists_below_lg(): void
    {
        $html = $this->render('{{ partial:stickyQuote }}');

        // Boven lg staat de knop al in de nav, die sinds 06-09-2026 meescrolt.
        $this->assertStringContainsString('lg:hidden', $html);
        $this->assertStringContainsString('/offerte', $html);
        $this->assertStringContainsString('btn btn--primary', $html);

        // Vast onderaan, boven de pagina-inhoud maar onder de nav (z-40 < z-50).
        $this->assertStringContainsString('fixed inset-x-0 bottom-0 z-40', $html);
    }

    public function test_it_stays_hidden_until_the_reader_is_past_the_second_section(): void
    {
        $html = $this->render('{{ partial:stickyQuote }}');

        // De grens komt uit de DOM en niet uit een vast aantal pixels: secties
        // verschillen sterk in hoogte, dus een vaste waarde zou op de ene
        // pagina te vroeg en op de andere te laat vallen.
        $this->assertStringContainsString("querySelectorAll('[data-section]')", $html);
        $this->assertStringContainsString('secties[1]', $html);

        // Met een plafond: op mobiel is de tweede sectie zo hoog dat de knop
        // anders pas bij de cta onderaan verschijnt.
        $this->assertStringContainsString('Math.min(bovenkant, window.innerHeight * 1.5)', $html);

        // Verborgen tot Alpine hem toont, zonder flits bij het laden.
        $this->assertStringContainsString('x-show="zichtbaar"', $html);
        $this->assertStringContainsString('x-cloak', $html);
    }

    public function test_the_layout_renders_it_on_every_page(): void
    {
        $layout = file_get_contents(resource_path('views/layout.antlers.html'));

        $this->assertStringContainsString('{{ partial:stickyQuote }}', $layout);
    }
}
