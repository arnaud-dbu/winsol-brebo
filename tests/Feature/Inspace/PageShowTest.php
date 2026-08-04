<?php

namespace Tests\Feature\Inspace;

use Statamic\Facades\Entry;
use Tests\Concerns\CreatesTemporaryContent;
use Tests\TestCase;

class PageShowTest extends TestCase
{
    use CreatesTemporaryContent;

    private const TOKEN = 'test-token-abc';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('inspace.tokens', ['test' => hash('sha256', self::TOKEN)]);
    }

    public function test_an_article_returns_its_mapped_fields(): void
    {
        $entry = $this->temporaryEntry('articles', 'detailtest-artikel', [
            'title' => 'Detailtest',
            'text' => 'De intro.',
            'themes' => ['zonwering'],
            'date' => '2026-08-04',
            'meta_title' => 'Meta',
            'redactor' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Body.']]],
            ],
        ]);

        $this->withToken(self::TOKEN)
            ->getJson('/api/inspace/v1/pages/'.$entry->id())
            ->assertOk()
            ->assertJsonPath('id', $entry->id())
            ->assertJsonPath('collection', 'articles')
            ->assertJsonPath('editable', true)
            ->assertJsonPath('title', 'Detailtest')
            ->assertJsonPath('intro', 'De intro.')
            ->assertJsonPath('theme', 'zonwering')
            ->assertJsonPath('meta_title', 'Meta')
            ->assertJsonPath('content.0.type', 'text')
            ->assertJsonPath('content.0.html', '<p>Body.</p>');
    }

    public function test_a_video_set_comes_back_as_an_opaque_box(): void
    {
        $entry = $this->temporaryEntry('articles', 'detailtest-video', [
            'title' => 'Video',
            'themes' => ['zonwering'],
            'date' => '2026-08-04',
            'redactor' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Voor.']]],
                ['type' => 'set', 'attrs' => ['id' => 'vid99', 'values' => ['type' => 'video', 'video' => 'https://youtu.be/x']]],
            ],
        ]);

        $this->withToken(self::TOKEN)
            ->getJson('/api/inspace/v1/pages/'.$entry->id())
            ->assertOk()
            ->assertJsonPath('content.1', ['type' => 'video', 'id' => 'vid99', 'opaque' => true]);
    }

    public function test_a_non_writable_entry_has_no_content(): void
    {
        $product = Entry::query()->where('collection', 'products')->first();

        $this->withToken(self::TOKEN)
            ->getJson('/api/inspace/v1/pages/'.$product->id())
            ->assertOk()
            ->assertJsonPath('editable', false)
            ->assertJsonPath('content', null);
    }

    public function test_an_unknown_id_gives_404(): void
    {
        $this->withToken(self::TOKEN)
            ->getJson('/api/inspace/v1/pages/bestaat-niet')
            ->assertStatus(404);
    }
}
