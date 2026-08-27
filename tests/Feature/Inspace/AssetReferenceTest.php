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

    /**
     * Zelfde vangnet als `PageCreateTest`: een snapshot van wat er vóór de
     * test al bestond, onafhankelijk van hoe `$created` gevuld raakt. Deze
     * suite raakt echte contentbestanden, en in dit traject heeft een test
     * al twee keer een écht artikel verwijderd omdat de opruiming blind elk
     * getrackt id wiste.
     *
     * @var list<string>
     */
    private array $preExistingArticleIds = [];

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

        $this->preExistingArticleIds = Entry::query()->where('collection', 'articles')->where('site', 'nl')->get()->map->id()->all();

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
            'theme' => 'realisaties',
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

    /**
     * De regressie uit de re-review: `GET /pages/{id}` geeft `image` terug
     * als het opgeslagen containerpad (`EntryMapper::read()` doet geen
     * omgekeerde vertaling naar id of url), niet in een van de twee vormen
     * die `POST /pages` accepteerde. Een integrator die een gelezen artikel
     * ongewijzigd terugstuurt — het normale patroon van een partiële update
     * — stuurt dus die derde vorm mee, ook als hij `image` niet eens wil
     * wijzigen, en liep vóór deze fix vast op een 422.
     */
    public function test_reading_back_an_image_and_patching_it_unchanged_still_works(): void
    {
        $media = $this->uploadMedia('roundtrip-image.jpg');
        $entryId = $this->postPage(['image' => $media['id']])->assertStatus(201)->json('id');

        $readBack = $this->withToken(self::TOKEN)
            ->getJson('/api/inspace/v1/pages/'.$entryId)
            ->json('image');

        $this->assertNotSame(
            $media['id'],
            $readBack,
            'GET moet het containerpad teruggeven, niet de id-vorm — anders bewijst deze test niets.'
        );

        $this->withToken(self::TOKEN)
            ->patchJson('/api/inspace/v1/pages/'.$entryId, [
                'image' => $readBack,
                'meta_title' => 'Nieuw',
            ])
            ->assertOk();

        $asset = Entry::find($entryId)->augmentedValue('image');

        $this->assertNotNull($asset);
        $this->assertSame($media['id'], $asset->id());
    }

    /**
     * Hetzelfde round-trip-scenario voor `meta_image`.
     */
    public function test_reading_back_a_meta_image_and_patching_it_unchanged_still_works(): void
    {
        $image = $this->uploadMedia('roundtrip-image-2.jpg');
        $metaImage = $this->uploadMedia('roundtrip-meta.jpg');

        $entryId = $this->postPage([
            'image' => $image['id'],
            'meta_image' => $metaImage['id'],
        ])->assertStatus(201)->json('id');

        $readBack = $this->withToken(self::TOKEN)
            ->getJson('/api/inspace/v1/pages/'.$entryId)
            ->json('meta_image');

        $this->assertNotSame($metaImage['id'], $readBack);

        $this->withToken(self::TOKEN)
            ->patchJson('/api/inspace/v1/pages/'.$entryId, [
                'meta_image' => $readBack,
                'meta_title' => 'Nieuw',
            ])
            ->assertOk();

        $asset = Entry::find($entryId)->augmentedValue('meta_image');

        $this->assertNotNull($asset);
        $this->assertSame($metaImage['id'], $asset->id());
    }
}
