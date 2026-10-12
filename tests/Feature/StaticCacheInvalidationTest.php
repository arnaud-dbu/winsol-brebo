<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Statamic\Events\EntrySaved;
use Statamic\Facades\Entry;
use Statamic\StaticCaching\Cacher;
use Statamic\StaticCaching\Invalidate;
use Tests\TestCase;

/**
 * Megamenu, footer, quicklinks en de overzichten tonen entries uit andere
 * collecties. Wist een opslag alleen de pagina van die entry, dan blijft een
 * nieuw artikel overal elders onzichtbaar tot de volgende deploy.
 *
 * Statamic abonneert de invalidatie alleen als er bij het opstarten al een
 * strategie staat. In tests is dat `null`, dus de test legt het abonnement zelf.
 */
class StaticCacheInvalidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.stores.static_cache' => ['driver' => 'array']]);
        config(['statamic.static_caching.strategy' => 'half']);
        Event::subscribe(Invalidate::class);
    }

    public function test_saving_an_article_clears_the_cached_homepage(): void
    {
        $this->get('/')->assertOk();
        $this->assertTrue($this->isCached('/'), 'De homepage komt niet in de statische cache.');

        $article = Entry::query()->where('collection', 'articles')->where('site', 'nl')->first();
        EntrySaved::dispatch($article);

        $this->assertFalse($this->isCached('/'), 'Na het opslaan van een artikel toont de homepage nog de oude menu’s en lijsten.');
    }

    /**
     * De actiebalk hangt af van de datum van vandaag. Zonder vervaldatum blijft
     * een afgelopen actie op elke gecachte pagina staan tot iemand iets opslaat.
     */
    public function test_a_cached_page_expires_within_an_hour(): void
    {
        $this->get('/')->assertOk();
        $this->assertTrue($this->isCached('/'));

        $this->travel(61)->minutes();

        $this->assertFalse($this->isCached('/'), 'Een gecachte pagina blijft langer dan een uur staan.');
    }

    private function isCached(string $path): bool
    {
        return app(Cacher::class)->hasCachedPage(Request::create(url($path)));
    }
}
