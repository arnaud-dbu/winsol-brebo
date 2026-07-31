<?php

namespace Tests\Feature\Sections;

class FooterTest extends SectionTestCase
{
    /**
     * Deze test draait via SectionTestCase::render(), en die helper roept een
     * kale view() aan — zonder Statamic-cascade. `{{ globals:… }}` is daar
     * altijd leeg, ongeacht wat er in content/globals/ staat. De Contact-
     * `footer__column` valt hier dus weg op zijn `{{ if }}`-guard, ook nu de
     * contactgegevens wél gevuld zijn. Op een echte pagerender
     * ($this->get(…)) verschijnt die kolom wel.
     *
     * Deze test dekt daarom de twee kolommen die zonder cascade gevuld zijn
     * (ranges + hoofdnavigatie) en de legal-links in het colofon.
     */
    public function test_renders_populated_link_columns_and_a_colophon(): void
    {
        $html = $this->render('{{ partial:footer }}');

        $this->assertSame(2, substr_count($html, 'footer__column'));
        $this->assertStringContainsString('footer__colophon', $html);

        // "BY BREBO" was een aparte, aria-hidden tekstspan naast het logo, maar
        // logo-inverse.svg tekent die merkregel zelf al als letterpaden. a257ed5
        // haalde de dubbel weg; wat blijft is de homelink met zijn toegankelijke
        // naam en het logo erin.
        $this->assertMatchesRegularExpression(
            '~<a href="/"[^>]*>\s*<span class="sr-only">Home Link</span>\s*<svg~',
            $html
        );

        // The `ranges` collection loop (9 seeded ranges under /aanbod/*).
        $this->assertSame(9, substr_count($html, 'href="/aanbod/'));

        // De `nav:main`-lus (Aanbod, Realisaties, Service, Over ons, Contact).
        $this->assertSame(5, substr_count($html, 'href="/aanbod"')
            + substr_count($html, 'href="/realisaties"')
            + substr_count($html, 'href="/service"')
            + substr_count($html, 'href="/over-ons"')
            + substr_count($html, 'href="/contact"'));
        $this->assertStringContainsString('Realisaties', $html);

        // The legal collection (3 seeded entries) renders in the colophon.
        $this->assertSame(3, substr_count($html, 'href="/cookie-policy"')
            + substr_count($html, 'href="/privacy-policy"')
            + substr_count($html, 'href="/toegankelijkheidsverklaring"'));
    }

    public function test_the_loan_simulator_sits_in_the_footer(): void
    {
        $html = $this->render('{{ partial:footer }}');

        $this->assertStringContainsString('Simuleer je lening', $html);
    }
}
