<?php

namespace Tests\Feature\Sections;

use Tests\Concerns\CreatesTemporaryContent;

class FooterTest extends SectionTestCase
{
    use CreatesTemporaryContent;

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
        // Nieuws staat alleen in de nav-lus zolang er een artikel is, dus de
        // test zet er zelf een neer (zie AppServiceProvider).
        $this->temporaryEntry('articles', 'footer-fixture', [
            'title' => 'Artikel voor de footer',
            'date' => '2026-01-01',
        ]);

        $html = $this->render('{{ partial:footer }}');

        $this->assertSame(2, substr_count($html, 'footer__column'));
        $this->assertStringContainsString('footer__colophon', $html);

        // "BY BREBO" was een aparte, aria-hidden tekstspan naast het logo, maar
        // logo-inverse.svg tekent die merkregel zelf al als letterpaden. a257ed5
        // haalde de dubbel weg; wat blijft is de homelink met zijn toegankelijke
        // naam en het logo erin.
        $this->assertMatchesRegularExpression(
            '~<a href="/"[^>]*>\s*<span class="sr-only">Home</span>\s*<svg~',
            $html
        );

        // The `ranges` collection loop: 8 gepubliceerde ranges onder
        // /aanbod/* — airco is gedepubliceerd (feedback Jimmy, 26-08-2026).
        $this->assertSame(8, substr_count($html, 'href="/aanbod/'));

        // De `nav:main`-lus (Aanbod, Nieuws, Service, Over ons, Contact).
        $this->assertSame(5, substr_count($html, 'href="/aanbod"')
            + substr_count($html, 'href="/nieuws"')
            + substr_count($html, 'href="/service"')
            + substr_count($html, 'href="/over-ons"')
            + substr_count($html, 'href="/contact"'));
        $this->assertStringContainsString('Nieuws', $html);

        // The legal collection (3 seeded entries) renders in the colophon.
        $this->assertSame(3, substr_count($html, 'href="/cookie-policy"')
            + substr_count($html, 'href="/privacy-policy"')
            + substr_count($html, 'href="/toegankelijkheidsverklaring"'));
    }

    public function test_the_loan_simulator_sits_in_the_footer(): void
    {
        $html = $this->render('{{ partial:footer }}');

        $this->assertStringContainsString('href="/simuleer-je-lening"', $html);
        $this->assertStringContainsString('Simuleer je lening', $html);
    }

    /**
     * Het adres stond als drie losse tags onder elkaar, en Antlers hield de
     * regelafbreking vlak vóór de komma aan: "Ninoofsesteenweg 637 , 1700
     * Dilbeek". Onzichtbaar zolang de globals leeg waren.
     */
    public function test_the_address_has_no_space_before_the_comma(): void
    {
        $html = $this->get('/contact')->assertOk()->getContent();

        $this->assertStringContainsString('Ninoofsesteenweg 637, 1700 Dilbeek', $html);
        $this->assertDoesNotMatchRegularExpression('/\s+,\s+1700 Dilbeek/', $html);
    }
    public function test_the_contact_column_lists_all_three_showrooms(): void
    {
        // Stond eerst op één adres uit de company-globals; Jimmy wil dat een
        // bezoeker de drie showrooms meteen ziet (feedback 05-09-2026).
        // Via een echte paginarender en niet `render()`: de losse partial
        // krijgt geen site-cascade mee, waardoor `collection:locations` daar
        // leeg blijft.
        $html = $this->get('/')->getContent();

        foreach ([
            'Ninoofsesteenweg 637, 1700 Dilbeek',
            'Bergensesteenweg 488, 1600 Sint-Pieters-Leeuw',
            'Boomsesteenweg 70, 2630 Aartselaar',
        ] as $adres) {
            $this->assertStringContainsString($adres, $html);
        }
    }
}
