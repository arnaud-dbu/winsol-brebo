<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Statamic\Events\AssetUploaded;
use Statamic\Facades\AssetContainer;
use Tests\TestCase;

/**
 * Beide tests halen hun container met `find()` op en bouwen hem niet met
 * `make()->save()`. Dat laatste schrijft `content/assets/{handle}.yaml`
 * letterlijk terug naar de werkkopie, met alleen de sleutels die de test zelf
 * zet — het overschreef zo stilletjes de schijfkeuze van de private-container
 * en maakte de privacygarantie ongedaan zodra de suite één keer gedraaid had.
 * `Storage::fake()` isoleert de bestanden; de containerdefinitie hoort uit de
 * echte configuratie te komen.
 */
class AssetUploadCompressionTest extends TestCase
{
    public function test_uploaded_jpeg_in_assets_container_is_compressed(): void
    {
        Storage::fake('r2');

        $container = AssetContainer::find('assets');

        $largeBytes = file_get_contents(base_path('tests/fixtures/images/large.jpg'));
        Storage::disk('r2')->put('uploads/photo.jpg', $largeBytes);

        $asset = $container->makeAsset('uploads/photo.jpg');
        $asset->save();

        AssetUploaded::dispatch($asset, 'photo.jpg');

        $stored = Storage::disk('r2')->get('uploads/photo.jpg');
        $this->assertLessThan(strlen($largeBytes), strlen($stored));

        $info = getimagesizefromstring($stored);
        $this->assertSame(2500, $info[0]);
    }

    public function test_assets_in_other_containers_are_untouched(): void
    {
        Storage::fake('r2_private');

        $container = AssetContainer::find('private');

        $largeBytes = file_get_contents(base_path('tests/fixtures/images/large.jpg'));
        Storage::disk('r2_private')->put('private/photo.jpg', $largeBytes);

        $asset = $container->makeAsset('private/photo.jpg');
        $asset->save();

        AssetUploaded::dispatch($asset, 'photo.jpg');

        $stored = Storage::disk('r2_private')->get('private/photo.jpg');
        $this->assertSame(strlen($largeBytes), strlen($stored));
    }
}
