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
}
