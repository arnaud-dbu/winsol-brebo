<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Statamic\Events\AssetUploaded;
use Statamic\Facades\AssetContainer;
use Tests\TestCase;

class AssetUploadCompressionTest extends TestCase
{
    public function test_uploaded_jpeg_in_assets_container_is_compressed(): void
    {
        Storage::fake('r2');

        $container = AssetContainer::make('assets')
            ->disk('r2')
            ->title('Assets');
        $container->save();

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
        Storage::fake('r2');

        $container = AssetContainer::make('private')
            ->disk('r2')
            ->title('Private');
        $container->save();

        $largeBytes = file_get_contents(base_path('tests/fixtures/images/large.jpg'));
        Storage::disk('r2')->put('private/photo.jpg', $largeBytes);

        $asset = $container->makeAsset('private/photo.jpg');
        $asset->save();

        AssetUploaded::dispatch($asset, 'photo.jpg');

        $stored = Storage::disk('r2')->get('private/photo.jpg');
        $this->assertSame(strlen($largeBytes), strlen($stored));
    }
}
