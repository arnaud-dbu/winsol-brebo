<?php

namespace Tests\Feature\Inspace;

use App\Providers\InspaceServiceProvider;
use Mockery;
use RuntimeException;
use Statamic\Contracts\Entries\Collection as CollectionContract;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Tests\Concerns\CreatesTemporaryContent;
use Tests\TestCase;

class PageUpdateTest extends TestCase
{
    use CreatesTemporaryContent;

    private const TOKEN = 'test-token-abc';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('inspace.tokens', ['test' => hash('sha256', self::TOKEN)]);
    }

    /**
     * @return array{0: \Statamic\Contracts\Entries\Entry, 1: list<array<string, mixed>>}
     */
    private function article(): array
    {
        $nodes = [
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Kop']]],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Eerste.']]],
            ['type' => 'set', 'attrs' => ['id' => 'vid77', 'enabled' => false, 'values' => ['type' => 'video', 'video' => 'https://youtu.be/x']]],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Tweede.']]],
        ];

        $entry = $this->temporaryEntry('articles', 'updatetest-artikel', [
            'title' => 'Updatetest',
            'themes' => ['zonwering'],
            'date' => '2026-08-04',
            'redactor' => $nodes,
        ]);

        return [$entry, $nodes];
    }

    public function test_an_unchanged_patch_leaves_the_storage_byte_identical(): void
    {
        [$entry, $nodes] = $this->article();

        $content = $this->withToken(self::TOKEN)
            ->getJson('/api/inspace/v1/pages/'.$entry->id())
            ->json('content');

        $this->withToken(self::TOKEN)
            ->patchJson('/api/inspace/v1/pages/'.$entry->id(), ['content' => $content])
            ->assertOk();

        $stored = Entry::find($entry->id())->get('redactor');

        $this->assertSame(json_encode($nodes), json_encode($stored));
        $this->assertStringNotContainsString('textAlign', json_encode($stored));
    }

    public function test_a_metadata_only_patch_does_not_touch_the_body(): void
    {
        [$entry, $nodes] = $this->article();

        $this->withToken(self::TOKEN)
            ->patchJson('/api/inspace/v1/pages/'.$entry->id(), ['meta_title' => 'Nieuw'])
            ->assertOk();

        $fresh = Entry::find($entry->id());

        $this->assertSame('Nieuw', $fresh->value('meta_title'));
        $this->assertSame(json_encode($nodes), json_encode($fresh->get('redactor')));
    }

    public function test_the_disabled_video_set_survives_a_rewrite(): void
    {
        [$entry, $nodes] = $this->article();

        $content = $this->withToken(self::TOKEN)
            ->getJson('/api/inspace/v1/pages/'.$entry->id())
            ->json('content');

        $content[0]['html'] = '<h2>Herschreven</h2><p>Anders.</p>';

        $this->withToken(self::TOKEN)
            ->patchJson('/api/inspace/v1/pages/'.$entry->id(), ['content' => $content])
            ->assertOk();

        $stored = Entry::find($entry->id())->get('redactor');
        $set = collect($stored)->firstWhere('type', 'set');

        $this->assertSame($nodes[2], $set, 'De uitgeschakelde set moet met row-id en enabled overleven.');
    }

    public function test_an_unknown_block_id_gives_422(): void
    {
        [$entry] = $this->article();

        $this->withToken(self::TOKEN)
            ->patchJson('/api/inspace/v1/pages/'.$entry->id(), [
                'content' => [['type' => 'video', 'id' => 'verzonnen', 'opaque' => true]],
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['content']]);
    }

    public function test_patching_a_non_writable_entry_gives_403(): void
    {
        $product = Entry::query()->where('collection', 'products')->first();

        $this->withToken(self::TOKEN)
            ->patchJson('/api/inspace/v1/pages/'.$product->id(), ['title' => 'Nee'])
            ->assertStatus(403)
            ->assertJsonPath('writable_collections', ['articles']);
    }

    /**
     * Kritisch punt 1: een set zonder `attrs.id` in de opslag is een
     * opslagdefect (bard-frontmatter met de hand geschreven), geen fout van
     * de aanroeper. De controller vangt daarom bewust alleen
     * `ExternalImageException` en `UnknownBlockException` op — deze
     * `MissingBlockIdException` moet ongevangen doorlopen naar een 500, niet
     * vermomd worden als een 422 die de client zogenaamd kan verhelpen door
     * zijn payload aan te passen.
     */
    public function test_a_missing_block_id_in_storage_is_not_disguised_as_a_client_error(): void
    {
        $entry = $this->temporaryEntry('articles', 'updatetest-corrupt', [
            'title' => 'Corrupt',
            'themes' => ['zonwering'],
            'date' => '2026-08-04',
            'redactor' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Voor.']]],
                ['type' => 'set', 'attrs' => ['values' => ['type' => 'video', 'video' => 'https://youtu.be/x']]],
            ],
        ]);

        $response = $this->withToken(self::TOKEN)
            ->patchJson('/api/inspace/v1/pages/'.$entry->id(), [
                'content' => [['type' => 'text', 'html' => '<p>Nieuw.</p>']],
            ]);

        $response->assertStatus(500);
        $this->assertNotSame(422, $response->status());
    }

    /**
     * Kritisch punt 3, eerste helft: `uniqueSlug()` krijgt bij een update
     * `$entry->id()` mee als te negeren id. Zonder dat zou de entry zelf
     * (met zijn eigen, ongewijzigde slug) als "botsing" gelden en een
     * achtervoegsel krijgen die er niet hoort te zijn.
     */
    public function test_resubmitting_the_same_slug_does_not_add_a_suffix(): void
    {
        [$entry] = $this->article();

        $this->withToken(self::TOKEN)
            ->patchJson('/api/inspace/v1/pages/'.$entry->id(), ['slug' => 'updatetest-artikel'])
            ->assertOk();

        $this->assertSame('updatetest-artikel', Entry::find($entry->id())->slug());
    }

    /**
     * Kritisch punt 3, tweede helft: wijzigt de slug wél naar een waarde die
     * al bij een ander artikel hoort, dan moet die botsing gewoon een
     * achtervoegsel krijgen — en het andere artikel blijft onaangeroerd.
     */
    public function test_changing_the_slug_into_an_existing_one_gets_suffixed(): void
    {
        [$entry] = $this->article();

        $other = $this->temporaryEntry('articles', 'ander-artikel', [
            'title' => 'Ander artikel',
            'themes' => ['zonwering'],
            'date' => '2026-08-04',
            'redactor' => [],
        ]);

        $this->withToken(self::TOKEN)
            ->patchJson('/api/inspace/v1/pages/'.$entry->id(), ['slug' => 'ander-artikel'])
            ->assertOk();

        $this->assertSame('ander-artikel-2', Entry::find($entry->id())->slug());
        $this->assertSame('ander-artikel', Entry::find($other->id())->slug());
    }

    /**
     * Kritisch punt 2: `apply()` slaat `slug`, `date` en `status` bewust over
     * en `update()` zet die drie daarna zelf, na de aanroep van `apply()`.
     * Deze test dekt de combinatie van alle vier tegelijk af (content plus
     * de drie metavelden in één PATCH), als regressietest op het
     * eindresultaat van die samenwerking.
     */
    public function test_slug_date_and_status_all_apply_together_with_a_content_change(): void
    {
        [$entry] = $this->article();

        $this->withToken(self::TOKEN)
            ->patchJson('/api/inspace/v1/pages/'.$entry->id(), [
                'slug' => 'gecombineerde-update',
                'date' => '2026-09-01',
                'status' => 'published',
                'content' => [['type' => 'text', 'html' => '<p>Alles tegelijk.</p>']],
            ])
            ->assertOk();

        $fresh = Entry::find($entry->id());

        $this->assertSame('gecombineerde-update', $fresh->slug());
        $this->assertSame('2026-09-01', $fresh->date()->toDateString());
        $this->assertTrue($fresh->published());
        $this->assertSame(
            [['type' => 'paragraph', 'attrs' => ['textAlign' => 'left'], 'content' => [['type' => 'text', 'text' => 'Alles tegelijk.']]]],
            $fresh->get('redactor')
        );
    }

    /**
     * Kritisch punt 4: `articles.yaml` zet `revisions: false` hard, dus een
     * test die alleen `config('statamic.revisions.enabled')` omzet raakt de
     * guard nooit (zie de weggelaten, zichzelf overslaande variant uit de
     * brief). Door `Collection::findByHandle()` te mocken testen we de
     * eigen logica van de provider — "gooi als een schrijfbare collectie
     * revisions aan heeft" — los van of er in dít project toevallig een
     * collectie met revisions aan geconfigureerd staat.
     */
    public function test_the_guard_refuses_when_a_writable_collection_has_revisions_enabled(): void
    {
        $collection = Mockery::mock(CollectionContract::class);
        $collection->shouldReceive('revisionsEnabled')->andReturn(true);

        Collection::shouldReceive('findByHandle')->with('articles')->andReturn($collection);

        $this->expectException(RuntimeException::class);

        (new InspaceServiceProvider($this->app))->boot();
    }

    public function test_the_guard_lets_the_app_boot_when_revisions_are_disabled(): void
    {
        $collection = Mockery::mock(CollectionContract::class);
        $collection->shouldReceive('revisionsEnabled')->andReturn(false);

        Collection::shouldReceive('findByHandle')->with('articles')->andReturn($collection);

        (new InspaceServiceProvider($this->app))->boot();

        $this->assertTrue(true);
    }
}
