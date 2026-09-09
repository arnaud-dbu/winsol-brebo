<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * `robots.txt` is een statisch bestand in `public/` en geen route. Het standaard
 * nginx-recept voor Laravel serveert dat pad rechtstreeks van schijf, zonder
 * `try_files`, en een route erop kreeg daardoor de 404 van nginx mee terwijl de
 * juiste tekst eronder stond. Deze toetsen gaan dus over de inhoud van het
 * bestand; de statuscode komt uit nginx en staat in
 * `.scratch/redirects/robots.sh`.
 */
class RobotsTest extends TestCase
{
    private function robots(): string
    {
        $pad = public_path('robots.txt');

        $this->assertFileExists($pad, 'Zonder dit bestand geeft nginx een 404 op /robots.txt.');

        return (string) file_get_contents($pad);
    }

    public function test_it_opens_the_site_up_for_every_crawler(): void
    {
        $inhoud = $this->robots();

        $this->assertStringContainsString('User-agent: *', $inhoud);
        $this->assertStringContainsString("Disallow:\n", $inhoud);
        $this->assertStringNotContainsString('Disallow: /', $inhoud);
    }

    public function test_it_names_the_sitemap_of_the_live_site(): void
    {
        $this->assertStringContainsString(
            'Sitemap: https://winsol-brebo.be/sitemap.xml',
            $this->robots(),
            'Het adres staat hier vast en volgt APP_URL niet meer, dus het hoort het live domein te zijn.'
        );
    }

    /**
     * Een route op dit pad wordt nooit geraakt: nginx serveert het bestand
     * ervoor. Hij zou er als de bron uitzien zonder het te zijn.
     */
    public function test_no_route_shadows_the_file(): void
    {
        $route = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($route) => $route->uri() === 'robots.txt');

        $this->assertNull($route, 'nginx serveert `public/robots.txt` rechtstreeks, dus deze route is dode code.');
    }

    /**
     * De vlag stuurt sinds de overstap naar een statisch bestand alleen nog de
     * `X-Robots-Tag`; `NoIndexHeaderTest` dekt beide takken daarvan.
     */
    public function test_the_default_leaves_a_site_indexable(): void
    {
        $this->assertTrue(config('app.indexable'), 'Zonder SITE_INDEXABLE moet een site gewoon indexeerbaar zijn.');
    }
}
