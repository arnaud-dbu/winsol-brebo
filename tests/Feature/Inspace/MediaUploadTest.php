<?php

namespace Tests\Feature\Inspace;

use Illuminate\Http\UploadedFile;
use Statamic\Facades\Asset;
use Tests\Concerns\CreatesTemporaryContent;
use Tests\TestCase;

class MediaUploadTest extends TestCase
{
    use CreatesTemporaryContent;

    private const TOKEN = 'test-token-abc';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('inspace.tokens', ['test' => hash('sha256', self::TOKEN)]);
        $this->fakeAssetDisk();
    }

    public function test_an_upload_returns_an_asset_id_and_stores_the_alt(): void
    {
        $response = $this->withToken(self::TOKEN)->postJson('/api/inspace/v1/media', [
            'file' => UploadedFile::fake()->image('nova-beeld.jpg', 1200, 800),
            'alt' => 'Een zip-screen op een zuidgevel',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['id', 'url', 'width', 'height', 'filename', 'alt'])
            ->assertJsonPath('alt', 'Een zip-screen op een zuidgevel');

        $asset = Asset::find($response->json('id'));

        $this->assertNotNull($asset);
        $this->assertSame('Een zip-screen op een zuidgevel', $asset->get('alt'));
        $this->assertStringStartsWith(config('inspace.assets.folder'), $asset->path());
    }

    public function test_a_pdf_is_rejected(): void
    {
        $this->withToken(self::TOKEN)
            ->postJson('/api/inspace/v1/media', [
                'file' => UploadedFile::fake()->create('brochure.pdf', 10, 'application/pdf'),
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['file']]);
    }

    public function test_a_pdf_renamed_to_look_like_an_image_is_still_rejected(): void
    {
        // mimes valideert de werkelijke inhoud (via een guess op de
        // magic bytes), niet de naam of de client-meegegeven extensie.
        $this->withToken(self::TOKEN)
            ->postJson('/api/inspace/v1/media', [
                'file' => UploadedFile::fake()->create('brochure.jpg', 10, 'application/pdf'),
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['file']]);
    }

    public function test_a_missing_file_is_rejected(): void
    {
        $this->withToken(self::TOKEN)
            ->postJson('/api/inspace/v1/media', ['alt' => 'zonder bestand'])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['file']]);
    }

    public function test_the_upload_goes_through_the_compression_listener(): void
    {
        $id = $this->withToken(self::TOKEN)->postJson('/api/inspace/v1/media', [
            'file' => UploadedFile::fake()->image('groot.jpg', 4000, 3000),
        ])->assertStatus(201)->json('id');

        $asset = Asset::find($id);

        $this->assertLessThanOrEqual(
            (int) config('image-compression.max_width'),
            $asset->width(),
            'De upload moet door AssetUploaded gaan, anders slaat CompressUploadedAsset over.'
        );
    }

    public function test_a_filename_with_a_path_traversal_segment_stays_inside_the_folder(): void
    {
        $file = UploadedFile::fake()->image('nova.jpg', 100, 100);

        // getClientOriginalName() geeft ongefilterd terug wat de client
        // meestuurt. Zonder basename() belandt deze string rechtstreeks in
        // Asset::path(), die op een `../`-segment een ongevangen
        // PathTraversalDetected-exception gooit (500) in plaats van gewoon
        // een asset in de eigen map te zetten.
        $response = $this->withToken(self::TOKEN)->postJson('/api/inspace/v1/media', [
            'file' => $this->renameUploadedFile($file, '../../../etc/evil.jpg'),
        ]);

        $response->assertStatus(201);

        $asset = Asset::find($response->json('id'));

        $this->assertNotNull($asset);
        $this->assertStringStartsWith(config('inspace.assets.folder').'/', $asset->path());
        $this->assertStringNotContainsString('..', $asset->path());
    }

    public function test_a_filename_with_a_subfolder_segment_stays_inside_the_folder(): void
    {
        $file = UploadedFile::fake()->image('nova.jpg', 100, 100);

        $response = $this->withToken(self::TOKEN)->postJson('/api/inspace/v1/media', [
            'file' => $this->renameUploadedFile($file, 'legal/cookie-policy.jpg'),
        ]);

        $response->assertStatus(201);

        $asset = Asset::find($response->json('id'));

        $this->assertNotNull($asset);
        $this->assertStringStartsWith(config('inspace.assets.folder').'/', $asset->path());
        $this->assertStringNotContainsString('/', $asset->basename());
    }

    public function test_a_second_upload_with_the_same_filename_does_not_overwrite_the_first(): void
    {
        $first = $this->withToken(self::TOKEN)->postJson('/api/inspace/v1/media', [
            'file' => UploadedFile::fake()->image('dubbel.jpg', 100, 100),
        ])->assertStatus(201);

        $second = $this->withToken(self::TOKEN)->postJson('/api/inspace/v1/media', [
            'file' => UploadedFile::fake()->image('dubbel.jpg', 100, 100),
        ])->assertStatus(201);

        $this->assertNotSame($first->json('id'), $second->json('id'));

        $firstAsset = Asset::find($first->json('id'));
        $secondAsset = Asset::find($second->json('id'));

        $this->assertNotNull($firstAsset);
        $this->assertNotNull($secondAsset);
        $this->assertTrue($firstAsset->disk()->exists($firstAsset->path()));
        $this->assertTrue($secondAsset->disk()->exists($secondAsset->path()));
    }

    /**
     * `UploadedFile::fake()` staat geen `/` in zijn eigen naam toe, dus om een
     * naam met schuine strepen of `../` te simuleren zoals een echte client
     * die zou meesturen, wordt hier alsnog met reflectie de originele
     * bestandsnaam overschreven.
     */
    private function renameUploadedFile(UploadedFile $file, string $name): UploadedFile
    {
        return new UploadedFile(
            $file->getRealPath(),
            $name,
            $file->getMimeType(),
            test: true,
        );
    }
}
