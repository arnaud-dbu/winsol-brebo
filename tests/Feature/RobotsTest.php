<?php

namespace Tests\Feature;

use Tests\TestCase;

class RobotsTest extends TestCase
{
    public function test_an_indexable_site_opens_up_and_names_the_sitemap_with_its_domain(): void
    {
        config()->set('app.indexable', true);
        config()->set('app.url', 'https://voorbeeld.test');

        $body = $this->get('/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=utf-8')
            ->getContent();

        $this->assertStringContainsString("Disallow:\n", $body);
        $this->assertStringNotContainsString('Disallow: /', $body);
        $this->assertStringContainsString('Sitemap: https://voorbeeld.test/sitemap.xml', $body);
    }

    public function test_a_non_indexable_site_closes_completely_and_hides_the_sitemap(): void
    {
        config()->set('app.indexable', false);

        $body = $this->get('/robots.txt')->assertOk()->getContent();

        $this->assertStringContainsString('Disallow: /', $body);
        $this->assertStringNotContainsString('Sitemap:', $body, 'Een afgesloten site hoort geen zoekmachine naar zijn sitemap te wijzen.');
    }

    /**
     * De vlag hangt bewust niet aan APP_ENV: staging draait daar ook op
     * `production`, dus daarop sturen zou de omgeving die het hardst
     * afgeschermd moet worden juist openzetten.
     */
    public function test_the_flag_is_independent_of_the_environment(): void
    {
        config()->set('app.env', 'production');
        config()->set('app.indexable', false);

        $this->assertStringContainsString('Disallow: /', $this->get('/robots.txt')->getContent());
    }

    public function test_the_default_leaves_a_site_indexable(): void
    {
        $this->assertTrue(config('app.indexable'), 'Zonder SITE_INDEXABLE moet een site gewoon indexeerbaar zijn.');
    }

    /**
     * `robots.txt` antwoordt in productie met een 404, en een bestand hier is
     * precies wat nginx zoekt: het zou die 404 oplossen. De statuscode zelf kan
     * geen toets in de suite zien, die komt uit nginx.
     */
    public function test_no_static_file_shadows_the_route(): void
    {
        $this->assertFileDoesNotExist(
            public_path('robots.txt'),
            'nginx serveert een bestand in `public` rechtstreeks, en zet daarmee de route en SITE_INDEXABLE buitenspel. De 404 hoort in de nginx-config opgelost te worden, niet met een bestand.'
        );
    }
}
