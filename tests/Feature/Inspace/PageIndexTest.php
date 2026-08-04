<?php

namespace Tests\Feature\Inspace;

use Tests\Concerns\CreatesTemporaryContent;
use Tests\TestCase;

class PageIndexTest extends TestCase
{
    use CreatesTemporaryContent;

    private const TOKEN = 'test-token-abc';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('inspace.tokens', ['test' => hash('sha256', self::TOKEN)]);
    }

    public function test_articles_are_listed_as_editable(): void
    {
        $entry = $this->temporaryEntry('articles', 'lijsttest-artikel', [
            'title' => 'Lijsttest artikel',
            'themes' => ['zonwering'],
            'date' => '2026-08-04',
        ]);

        $row = collect(
            $this->withToken(self::TOKEN)
                ->getJson('/api/inspace/v1/pages?per_page=200')
                ->assertOk()
                ->json('data')
        )->firstWhere('id', $entry->id());

        $this->assertNotNull($row, 'Het aangemaakte artikel moet in de lijst staan.');
        $this->assertTrue($row['editable']);
        $this->assertSame('articles', $row['collection']);
        $this->assertStringContainsString('/nieuws/lijsttest-artikel', $row['url']);
    }

    public function test_other_collections_are_listed_but_not_editable(): void
    {
        $rows = $this->withToken(self::TOKEN)
            ->getJson('/api/inspace/v1/pages?collection=products&per_page=200')
            ->assertOk()
            ->json('data');

        $this->assertNotEmpty($rows, 'Producten moeten leesbaar zijn: Nova legt er interne links naartoe.');

        foreach ($rows as $row) {
            $this->assertFalse($row['editable']);
        }
    }

    public function test_pagination_caps_at_two_hundred(): void
    {
        $meta = $this->withToken(self::TOKEN)
            ->getJson('/api/inspace/v1/pages?per_page=5000')
            ->assertOk()
            ->json('meta');

        $this->assertSame(200, $meta['per_page']);
    }

    public function test_quicklinks_have_no_url(): void
    {
        $rows = $this->withToken(self::TOKEN)
            ->getJson('/api/inspace/v1/pages?collection=quicklinks&per_page=200')
            ->assertOk()
            ->json('data');

        foreach ($rows as $row) {
            $this->assertNull($row['url'], 'quicklinks heeft geen route en is dus geen bruikbaar linkdoel.');
        }
    }

    public function test_the_default_site_is_accepted_and_an_unknown_one_is_rejected(): void
    {
        $this->withToken(self::TOKEN)
            ->getJson('/api/inspace/v1/pages?site=nl&per_page=1')
            ->assertOk();

        $this->withToken(self::TOKEN)
            ->getJson('/api/inspace/v1/pages?site=fr&per_page=1')
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['site']]);
    }

    /**
     * `array_slice($offset, -5)` betekent "stop 5 vóór het einde", geen
     * paginering — een negatieve of nul `per_page` moet dus naar 1 geklemd
     * worden, niet alleen in `meta.per_page`, maar ook in het werkelijke
     * aantal rijen dat terugkomt. Vier extra artikelen bovenop de zes uit
     * `content/collections/articles` maken het verschil aantoonbaar: zonder
     * de onderkap zou `per_page=-5` vijf rijen teruggeven (10 - 5) in plaats
     * van 1.
     */
    public function test_negative_and_zero_per_page_are_clamped_to_one(): void
    {
        foreach (range(1, 4) as $i) {
            $this->temporaryEntry('articles', "onderkap-test-artikel-{$i}", [
                'title' => "Onderkap test artikel {$i}",
                'themes' => ['zonwering'],
                'date' => '2026-08-04',
            ]);
        }

        $negative = $this->withToken(self::TOKEN)
            ->getJson('/api/inspace/v1/pages?collection=articles&per_page=-5')
            ->assertOk();

        $this->assertSame(1, $negative->json('meta.per_page'));
        $this->assertCount(1, $negative->json('data'));

        $zero = $this->withToken(self::TOKEN)
            ->getJson('/api/inspace/v1/pages?collection=articles&per_page=0')
            ->assertOk();

        $this->assertSame(1, $zero->json('meta.per_page'));
        $this->assertCount(1, $zero->json('data'));
    }

    /**
     * `collection` en `site` zijn de enige queryparameters die ongefilterd
     * in een typed `?string`-parameter belanden (`EntryLister::handles()`,
     * `SiteGuard::resolve()`). Vóór de fix gaf een array hier een kale 500
     * TypeError in plaats van een 422.
     */
    public function test_an_array_collection_query_parameter_gives_422_instead_of_a_500(): void
    {
        $this->withToken(self::TOKEN)
            ->getJson('/api/inspace/v1/pages?collection[]=a')
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['collection']]);
    }

    public function test_an_array_site_query_parameter_gives_422_instead_of_a_500(): void
    {
        $this->withToken(self::TOKEN)
            ->getJson('/api/inspace/v1/pages?site[]=nl')
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['site']]);
    }
}
