<?php

namespace Tests\Feature\Inspace;

use App\Inspace\EntryWriter;
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

    /**
     * Momentopname van wat er vóór de test al bestond. Zuiver op zichzelf
     * volstaat "alleen bij 201 tracken" niet als vangnet: mocht de
     * duplicaatdetectie ooit tóch een bestaand artikel matchen en dat id
     * alsnog in `$created` belandt, dan mag de opruiming dat artikel nooit
     * verwijderen. Deze snapshot is onafhankelijk van hoe `$created` gevuld
     * raakt en beschermt dus ook tegen een fout die hierin zelf zou sluipen.
     *
     * @var list<string>
     */
    private array $preExistingArticleIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('inspace.tokens', ['test' => hash('sha256', self::TOKEN)]);

        $this->preExistingArticleIds = Entry::query()->where('collection', 'articles')->get()->map->id()->all();

        $this->beforeApplicationDestroyed(function (): void {
            foreach ($this->created as $id) {
                if (in_array($id, $this->preExistingArticleIds, true)) {
                    continue;
                }

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

        // Alleen een 201 betekent dat dit endpoint zelf een nieuwe entry
        // aanmaakte. Een 200 is het "bestond al"-pad van de
        // duplicaatdetectie: dat id kan een artikel zijn dat allang bestond
        // en dat deze test dus niet mag opruimen.
        if ($response->status() === 201) {
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
        $first = Entry::find($this->postPage($this->payload(['title' => '!!!']))->assertStatus(201)->json('id'));
        $second = Entry::find($this->postPage($this->payload(['title' => '???']))->assertStatus(201)->json('id'));

        // `assertNotSame('', ...)` verdraagt ook een `null`-slug: Statamic's
        // slug-setter maakt van een lege string géén lege slug maar geen
        // slug tout court, waarmee de entry terugvalt op de mount-url van de
        // collectie ("/nieuws") in plaats van zijn eigen, bereikbare pagina.
        // Vandaar hier expliciet een vorm-check én een url-check, in plaats
        // van alleen "niet gelijk aan lege string".
        $this->assertMatchesRegularExpression('/^[a-z0-9]+(-[a-z0-9]+)*$/', (string) $first->slug());
        $this->assertNotSame('/nieuws', $first->url());

        // Twee entries die allebei op een lege slugbasis uitkomen mogen
        // elkaar niet overschrijven of dezelfde willekeurige basis delen.
        $this->assertNotSame($first->slug(), $second->slug());
    }

    public function test_an_empty_external_id_does_not_collide_with_a_later_empty_external_id(): void
    {
        // Over HTTP bereikt een lege string dit veld nooit: de globale
        // `ConvertEmptyStringsToNull`-middleware zet 'm al om naar `null`
        // vóór de request de controller bereikt (geverifieerd tegen
        // `Illuminate\Foundation\Configuration\Middleware::getGlobalMiddleware()`,
        // niet uitgezet in `bootstrap/app.php`). Dit pad is dus alleen
        // bereikbaar door `EntryWriter::create()` rechtstreeks aan te
        // roepen, zoals een toekomstige console-aanroeper zou doen.
        $writer = app(EntryWriter::class);

        $first = $writer->create('articles', $this->payload(['external_id' => '']));

        // Alleen tracken wat deze aanroep zelf aanmaakte: `existing` vertelt
        // precies of dat zo is. Was de duplicaatcheck onverhoopt tóch
        // uitgekomen bij een bestaand artikel, dan mag dat artikel hier niet
        // in de opruimlijst belanden.
        if (! $first['existing']) {
            $this->created[] = $first['entry']->id();
        }

        $second = $writer->create('articles', $this->payload(['external_id' => '']));

        if (! $second['existing']) {
            $this->created[] = $second['entry']->id();
        }

        $this->assertNotSame($first['entry']->id(), $second['entry']->id());
        $this->assertFalse($first['existing']);
        $this->assertFalse($second['existing']);
    }

    public function test_multiple_changed_blocks_all_report_their_warnings(): void
    {
        $response = $this->postPage($this->payload([
            'content' => [
                ['type' => 'text', 'html' => '<h1>Te groot</h1><p>Eerste.</p>'],
                ['type' => 'text', 'html' => '<p><img src="asset::'.$this->anyAssetId().'" alt="Genegeerd"></p>'],
            ],
        ]))->assertStatus(201);

        $warnings = $response->json('warnings');

        $this->assertContains('Tag <h1> is niet toegestaan en is verwijderd.', $warnings);
        $this->assertContains('Het alt-attribuut op een <img> is genegeerd. Zet de alt-tekst bij de upload via POST /media.', $warnings);
    }

    public function test_multiple_changed_blocks_report_warnings_in_reverse_order_too(): void
    {
        $response = $this->postPage($this->payload([
            'content' => [
                ['type' => 'text', 'html' => '<p><img src="asset::'.$this->anyAssetId().'" alt="Genegeerd"></p>'],
                ['type' => 'text', 'html' => '<h1>Te groot</h1><p>Tweede.</p>'],
            ],
        ]))->assertStatus(201);

        $warnings = $response->json('warnings');

        $this->assertContains('Tag <h1> is niet toegestaan en is verwijderd.', $warnings);
        $this->assertContains('Het alt-attribuut op een <img> is genegeerd. Zet de alt-tekst bij de upload via POST /media.', $warnings);
    }

    public function test_a_valid_site_is_accepted(): void
    {
        $this->postPage($this->payload(['site' => 'nl']))->assertStatus(201);
    }

    public function test_an_unknown_site_gives_422(): void
    {
        $this->postPage($this->payload(['site' => 'fr']))
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['site']]);
    }
}
