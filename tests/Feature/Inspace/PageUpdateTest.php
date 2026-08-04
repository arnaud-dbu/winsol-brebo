<?php

namespace Tests\Feature\Inspace;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Sleep;
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
            'themes' => ['realisaties'],
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

    /**
     * Reviewfixronde: `PATCH` met een tekstblok zonder `html` gaf vóór de fix
     * een `200` met lege `warnings` en overschreef de opgeslagen `redactor`
     * met `[]` — geruisloos contentverlies op een gepubliceerd artikel, mét
     * succesantwoord. `content.*.html` is nu verplicht zodra `type` `text` is.
     */
    public function test_a_text_block_without_html_gives_422_and_leaves_the_body_untouched(): void
    {
        [$entry, $nodes] = $this->article();

        $this->withToken(self::TOKEN)
            ->patchJson('/api/inspace/v1/pages/'.$entry->id(), ['content' => [['type' => 'text']]])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['content.0.html']]);

        $fresh = Entry::find($entry->id());

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
            'themes' => ['realisaties'],
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
            'themes' => ['realisaties'],
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
     * Punt 2 uit de reviewfixronde: `store()` ving `WriteLockTimeoutException`
     * al af en gaf een 503 met "probeer opnieuw"; `update()` deed dat niet en
     * liet 'm ongevangen doorlopen naar een kale 500. `Sleep::fake(true, true)`
     * synct de faked sleep met Carbon zodat `Cache\Lock::block()` zijn eigen
     * timeout-venster "doorloopt" zonder er echt 10 seconden op te wachten.
     */
    public function test_a_lock_held_by_another_write_gives_503_instead_of_a_500(): void
    {
        [$entry] = $this->article();

        Sleep::fake(true, true);

        $lock = Cache::lock('inspace:write:articles', 30);
        $this->assertTrue($lock->get());

        try {
            $this->withToken(self::TOKEN)
                ->patchJson('/api/inspace/v1/pages/'.$entry->id(), ['meta_title' => 'Nieuw'])
                ->assertStatus(503);
        } finally {
            $lock->release();
        }
    }
}
