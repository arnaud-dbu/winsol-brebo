<?php

namespace Tests\Feature\Inspace;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_a_filename_that_reduces_to_a_dot_is_rejected(): void
    {
        // Zonder deze check bouwt de controller `folder/.`, wat via
        // pathinfo() neerkomt op de map zelf: de schrijfactie mikt dan op de
        // map in plaats van een bestand daarin ("Is a directory").
        //
        // `$source` blijft hier als losse variabele staan (in plaats van
        // inline doorgegeven te worden): het onderliggende `tmpfile()` van
        // `UploadedFile::fake()` verdwijnt van disk zodra dat object geen
        // referenties meer heeft, en `renameUploadedFile()` bouwt alleen een
        // nieuwe wrapper om hetzelfde pad — de bytes moeten dus blijven
        // bestaan tot ná de request.
        $source = UploadedFile::fake()->image('nova.jpg', 100, 100);

        $this->withToken(self::TOKEN)
            ->postJson('/api/inspace/v1/media', [
                'file' => $this->renameUploadedFile($source, '.'),
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['file']]);

        $this->assertContainerIsEmpty();
    }

    public function test_a_filename_that_reduces_to_dot_dot_is_rejected(): void
    {
        $source = UploadedFile::fake()->image('nova.jpg', 100, 100);

        $this->withToken(self::TOKEN)
            ->postJson('/api/inspace/v1/media', [
                'file' => $this->renameUploadedFile($source, '..'),
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['file']]);

        $this->assertContainerIsEmpty();
    }

    public function test_an_empty_filename_is_rejected(): void
    {
        // Een lege naam laat de controller buiten `folder` schrijven (het
        // pad valt terug op de map zelf als bestandsnaam) en leverde vóór
        // deze fix een 201 op met een asset dat er in de praktijk niet echt
        // "is" — nergens vindbaar onder de eigen map, met een onzinnige id.
        $source = UploadedFile::fake()->image('nova.jpg', 100, 100);

        $this->withToken(self::TOKEN)
            ->postJson('/api/inspace/v1/media', [
                'file' => $this->renameUploadedFile($source, ''),
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['file']]);

        $this->assertContainerIsEmpty();
    }

    public function test_a_corrupt_image_body_is_rejected_and_leaves_no_orphan_asset(): void
    {
        // Geldige magic bytes (finfo herkent dit als image/jpeg, dus `mimes`
        // laat het door) maar een afgekapte body. Statamic decodeert de
        // bytes synchroon tijdens save() voor de dimensies/preview
        // (Imaging\ImageGenerator), en dat gooit hier een DecoderException
        // ná het wegschrijven van het bestand.
        $this->withToken(self::TOKEN)
            ->postJson('/api/inspace/v1/media', [
                'file' => $this->corruptJpeg(),
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['file']]);

        $this->assertContainerIsEmpty();
    }

    /**
     * Telt bestanden op de faked disk zelf, niet via `$container->assets()`:
     * die query leest Stache's eigen store, wat een cache is van de
     * productieschijf en dus geen rekening houdt met `Storage::fake()`.
     */
    private function assertContainerIsEmpty(): void
    {
        $this->assertEmpty(Storage::disk('r2')->allFiles());
    }

    /**
     * Een bestand met een geldige JPEG-header maar zonder de rest van de
     * beeldgegevens: genoeg voor `finfo`/`mimes` om het als `image/jpeg` te
     * herkennen, te weinig voor GD om het te decoderen.
     */
    private function corruptJpeg(): UploadedFile
    {
        $image = imagecreatetruecolor(50, 50);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 0, 0));
        ob_start();
        imagejpeg($image);
        $bytes = substr(ob_get_clean(), 0, 50);
        imagedestroy($image);

        $path = tempnam(sys_get_temp_dir(), 'corrupt').'.jpg';
        file_put_contents($path, $bytes);

        return new UploadedFile($path, 'corrupt.jpg', 'image/jpeg', test: true);
    }

    /**
     * `UploadedFile::fake()` staat geen `.`, `..` of een lege naam als eigen
     * naam toe, dus om zo'n clientnaam te simuleren wordt hier de originele
     * bestandsnaam overschreven op een al bestaand (geldig) bestand.
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
