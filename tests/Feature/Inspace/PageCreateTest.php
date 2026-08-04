<?php

namespace Tests\Feature\Inspace;

use Illuminate\Testing\TestResponse;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Entry;
use Tests\Concerns\CreatesTemporaryContent;
use Tests\TestCase;

class PageCreateTest extends TestCase
{
    use CreatesTemporaryContent;

    private const TOKEN = 'test-token-abc';

    /** @var list<string> */
    private array $created = [];

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('inspace.tokens', ['test' => hash('sha256', self::TOKEN)]);

        $this->beforeApplicationDestroyed(function (): void {
            foreach ($this->created as $id) {
                Entry::find($id)?->delete();
            }
        });
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Nova schrijft een artikel',
            'theme' => 'zonwering',
            'image' => $this->anyAssetId(),
            'content' => [['type' => 'text', 'html' => '<h2>Kop</h2><p>Body.</p>']],
            'status' => 'draft',
        ], $overrides);
    }

    private function anyAssetId(): string
    {
        return AssetContainer::findByHandle('assets')->assets()->first()->id();
    }

    private function postPage(array $payload): TestResponse
    {
        $response = $this->withToken(self::TOKEN)->postJson('/api/inspace/v1/pages', $payload);

        if ($response->json('id') !== null) {
            $this->created[] = $response->json('id');
        }

        return $response;
    }

    public function test_a_minimal_payload_creates_a_draft_article(): void
    {
        $response = $this->postPage($this->payload())->assertStatus(201);

        $entry = Entry::find($response->json('id'));

        $this->assertNotNull($entry);
        $this->assertSame('Nova schrijft een artikel', $entry->value('title'));
        $this->assertSame(['zonwering'], $entry->get('themes'));
        $this->assertFalse($entry->published());
        $this->assertSame('nova-schrijft-een-artikel', $entry->slug());
        $this->assertNotNull($response->json('url'));
    }

    public function test_a_missing_theme_gives_422_with_the_valid_values(): void
    {
        $payload = $this->payload();
        unset($payload['theme']);

        $response = $this->postPage($payload)->assertStatus(422);

        $this->assertArrayHasKey('theme', $response->json('errors'));
    }

    public function test_an_unknown_theme_gives_422(): void
    {
        $this->postPage($this->payload(['theme' => 'bestaat-niet']))
            ->assertStatus(422)
            ->assertJsonPath('errors.theme.0', 'Onbekend thema. Geldige waarden: energie-en-comfort, ramen-en-deuren, terrasoverkapping, zonwering.');
    }

    public function test_a_missing_image_gives_422(): void
    {
        $payload = $this->payload();
        unset($payload['image']);

        $this->postPage($payload)->assertStatus(422)->assertJsonStructure(['errors' => ['image']]);
    }

    public function test_an_unknown_field_gives_422(): void
    {
        $this->postPage($this->payload(['categories' => ['nieuws']]))
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['categories']]);
    }

    public function test_an_external_image_gives_422(): void
    {
        $this->postPage($this->payload([
            'content' => [['type' => 'text', 'html' => '<p><img src="https://cdn.example.com/x.jpg"></p>']],
        ]))->assertStatus(422)->assertJsonStructure(['errors' => ['content']]);
    }

    public function test_a_slug_collision_gets_a_suffix_and_overwrites_nothing(): void
    {
        $first = $this->postPage($this->payload())->assertStatus(201)->json('id');
        $second = $this->postPage($this->payload(['external_id' => 'anders']))->assertStatus(201)->json('id');

        $this->assertNotSame($first, $second);
        $this->assertSame('nova-schrijft-een-artikel-2', Entry::find($second)->slug());
    }

    public function test_the_same_external_id_returns_the_existing_article(): void
    {
        $first = $this->postPage($this->payload(['external_id' => 'nova-4711']))->assertStatus(201)->json('id');

        $second = $this->postPage($this->payload(['external_id' => 'nova-4711']))->assertStatus(200);

        $this->assertSame($first, $second->json('id'));
        $this->assertCount(1, Entry::query()->where('collection', 'articles')->where('external_id', 'nova-4711')->get());
    }

    public function test_disallowed_html_is_stripped_and_reported(): void
    {
        $response = $this->postPage($this->payload([
            'content' => [['type' => 'text', 'html' => '<h1>Te groot</h1><p>Blijft.</p>']],
        ]))->assertStatus(201);

        $this->assertContains('Tag <h1> is niet toegestaan en is verwijderd.', $response->json('warnings'));
    }

    public function test_an_empty_slug_source_still_creates_a_reachable_entry(): void
    {
        $response = $this->postPage($this->payload(['title' => '!!!']))->assertStatus(201);

        $entry = Entry::find($response->json('id'));

        $this->assertNotNull($entry);
        $this->assertNotSame('', $entry->slug());
    }

    public function test_an_empty_external_id_does_not_collide_with_a_later_empty_external_id(): void
    {
        $first = $this->postPage($this->payload(['external_id' => '']))->assertStatus(201)->json('id');
        $second = $this->postPage($this->payload(['external_id' => '']))->assertStatus(201)->json('id');

        $this->assertNotSame($first, $second);
    }
}
