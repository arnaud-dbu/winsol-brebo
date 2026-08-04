<?php

namespace Tests\Feature\Inspace;

use App\Http\Middleware\InspaceRevisionsGuard;
use Illuminate\Http\Request;
use Mockery;
use Statamic\Contracts\Entries\Collection as CollectionContract;
use Statamic\Facades\Collection;
use Statamic\Facades\Stache;
use Tests\TestCase;

class RevisionsGuardTest extends TestCase
{
    private const TOKEN = 'test-token-abc';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('inspace.tokens', ['test' => hash('sha256', self::TOKEN)]);
    }

    public function test_the_guard_refuses_when_a_writable_collection_has_revisions_enabled(): void
    {
        $collection = Mockery::mock(CollectionContract::class);
        $collection->shouldReceive('revisionsEnabled')->andReturn(true);

        Collection::shouldReceive('findByHandle')->with('articles')->andReturn($collection);

        $response = (new InspaceRevisionsGuard)->handle(
            Request::create('/api/inspace/v1/pages', 'POST'),
            fn () => response()->json(['ok' => true])
        );

        $this->assertSame(503, $response->getStatusCode());
        $this->assertSame('articles', json_decode((string) $response->getContent(), true)['collection']);
    }

    public function test_the_guard_lets_the_request_through_when_revisions_are_disabled(): void
    {
        $collection = Mockery::mock(CollectionContract::class);
        $collection->shouldReceive('revisionsEnabled')->andReturn(false);

        Collection::shouldReceive('findByHandle')->with('articles')->andReturn($collection);

        $response = (new InspaceRevisionsGuard)->handle(
            Request::create('/api/inspace/v1/pages', 'POST'),
            fn () => response()->json(['ok' => true])
        );

        $this->assertSame(200, $response->getStatusCode());
    }

    /**
     * Kern van de wijziging: de guard hangt alleen aan de schrijfroutes.
     * `revisions: false` staat hard in `articles.yaml`, en `Collection::
     * findByHandle()` deserialiseert bij elke aanroep een verse kopie uit
     * `Stache::cacheStore()` — een object in het geheugen aanpassen zonder
     * dat cache-item te herschrijven heeft dus geen effect op de vólgende
     * aanroep (empirisch bevestigd: zonder de `cacheStore()->forever()`-stap
     * hieronder bleef de POST gewoon door de guard heen lopen). Om het
     * geblokkeerde pad via een echte HTTP-request te bewijzen wordt de
     * gewijzigde collectie daarom expliciet teruggeschreven naar diezelfde
     * cache-sleutel, en na de test weer vergeten zodat latere tests de
     * originele `revisions: false` uit het schijfbestand teruglezen.
     */
    public function test_a_get_route_ignores_the_guard_while_a_write_route_is_blocked(): void
    {
        config()->set('statamic.revisions.enabled', true);

        $collection = Collection::findByHandle('articles');
        $collection->revisionsEnabled(true);

        $cacheKey = 'stache::items::collections::articles';
        Stache::cacheStore()->forever($cacheKey, $collection);

        $this->beforeApplicationDestroyed(function () use ($cacheKey): void {
            Stache::cacheStore()->forget($cacheKey);
        });

        $this->withToken(self::TOKEN)
            ->getJson('/api/inspace/v1/pages')
            ->assertOk();

        $this->withToken(self::TOKEN)
            ->postJson('/api/inspace/v1/pages', ['title' => 'Nee'])
            ->assertStatus(503)
            ->assertJsonPath('collection', 'articles');
    }
}
