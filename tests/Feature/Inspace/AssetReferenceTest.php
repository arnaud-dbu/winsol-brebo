<?php

namespace Tests\Feature\Inspace;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Statamic\Facades\Entry;
use Tests\Concerns\CreatesTemporaryContent;
use Tests\TestCase;

class AssetReferenceTest extends TestCase
{
    use CreatesTemporaryContent;

    private const TOKEN = 'test-token-abc';

    /** @var list<string> */
    private array $created = [];

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('inspace.tokens', ['test' => hash('sha256', self::TOKEN)]);
        $this->fakeAssetDisk();

        // `fakeAssetDisk()` faket zonder `url`-config, waardoor
        // `AssetContainer::accessible()` de container als privé beschouwt en
        // `asset->url()` `null` teruggeeft. De url-vorm die deze klasse test
        // heeft een echte url nodig, dus hier opnieuw faken mét een url.
        Storage::fake('r2', ['url' => 'https://cdn.test/assets']);

        $this->beforeApplicationDestroyed(function (): void {
            foreach ($this->created as $id) {
                Entry::find($id)?->delete();
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function uploadMedia(string $filename = 'review-hero.jpg'): array
    {
        return $this->withToken(self::TOKEN)
            ->postJson('/api/inspace/v1/media', [
                'file' => UploadedFile::fake()->image($filename, 1200, 800),
            ])
            ->assertStatus(201)
            ->json();
    }

    /**
     * Tracked bij elk antwoord dat wél een id teruggeeft, ook als de test
     * eigenlijk een 422 verwacht: een gebroken fix (opnieuw een 201 op een
     * niet-bestaand asset) mag geen echt artikel in `content/` achterlaten.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function postPage(array $overrides): TestResponse
    {
        $response = $this->withToken(self::TOKEN)->postJson('/api/inspace/v1/pages', array_merge([
            'title' => 'Nova koppelt een beeld',
            'theme' => 'zonwering',
            'content' => [['type' => 'text', 'html' => '<p>Body.</p>']],
            'status' => 'draft',
        ], $overrides));

        if ($response->json('id') !== null) {
            $this->created[] = $response->json('id');
        }

        return $response;
    }

    /**
     * De volledige, gedocumenteerde flow uit README.md: upload via
     * `POST /media`, zet de teruggegeven `id` in `image` op `POST /pages`.
     * Vóór de fix sloeg `EntryWriter::normalize()` die id-vorm
     * (`assets::inspace/...`) rauw op in een assets-veld dat een
     * containerpad verwacht: `augmentedValue('image')` gaf dan `null` en de
     * header rendert zonder beeld ondanks een 201.
     */
    public function test_the_documented_upload_then_publish_flow_resolves_to_a_real_asset(): void
    {
        $media = $this->uploadMedia();

        $entryId = $this->postPage(['image' => $media['id']])->assertStatus(201)->json('id');

        $entry = Entry::find($entryId);
        $asset = $entry->augmentedValue('image');

        $this->assertNotNull($asset, 'augmentedValue(image) moet een Asset teruggeven, geen null.');
        $this->assertSame($media['id'], $asset->id());
    }

    /**
     * De id-vorm is niet de enige geldige invoer: `POST /media` geeft ook een
     * `url` terug, en die moet even goed vertaald worden naar het
     * containerpad dat het fieldtype verwacht.
     */
    public function test_the_media_url_is_also_accepted_and_resolves_to_the_same_asset(): void
    {
        $media = $this->uploadMedia('review-hero-url.jpg');

        $entryId = $this->postPage(['image' => $media['url']])->assertStatus(201)->json('id');

        $asset = Entry::find($entryId)->augmentedValue('image');

        $this->assertNotNull($asset);
        $this->assertSame($media['id'], $asset->id());
    }

    /**
     * Sluit de eerder uitgestelde bevinding: een `image` die niet naar een
     * bestaand asset wijst mocht niet langer stil een artikel met een lege
     * hero publiceren.
     */
    public function test_an_image_reference_to_a_nonexistent_asset_gives_422(): void
    {
        $this->postPage(['image' => 'bestaat/niet.jpg'])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['image']]);
    }

    /**
     * Hetzelfde geldt voor `meta_image`: die valt anders stil terug op de
     * sitedefault zonder dat de aanroeper dat kan zien.
     */
    public function test_a_nonexistent_meta_image_reference_gives_422(): void
    {
        $this->postPage([
            'image' => $this->uploadMedia('meta-check.jpg')['id'],
            'meta_image' => 'bestaat/ook/niet.jpg',
        ])->assertStatus(422)->assertJsonStructure(['errors' => ['meta_image']]);
    }
}
