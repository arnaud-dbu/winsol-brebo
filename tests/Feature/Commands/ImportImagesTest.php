<?php

namespace Tests\Feature\Commands;

use Illuminate\Support\Facades\File;
use Statamic\Facades\AssetContainer;
use Tests\Concerns\CreatesTemporaryContent;
use Tests\TestCase;

/**
 * Elke test importeert naar zijn eigen mapnaam. De `assets`-container
 * gebruikt de gedeelde `file_testing`-cache (zie tests/bootstrap.php), die
 * alleen vóór de hele suite wordt gewist, niet tussen tests. Delen tests
 * dezelfde container/folder-combinatie, dan kan `asset()` in een latere test
 * een sinds lang verlopen "bestaat al" uit een eerdere test teruggeven — ook
 * al is de fake-disk van die eerdere test intussen weg.
 */
class ImportImagesTest extends TestCase
{
    use CreatesTemporaryContent;

    private string $source;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeAssetDisk();

        $this->source = storage_path('framework/testing/import-source');
        File::ensureDirectoryExists($this->source);
        File::copy(base_path('tests/fixtures/images/watermarked.jpg'), $this->source.'/watermarked.jpg');
        File::copy(base_path('tests/fixtures/images/clean.jpg'), $this->source.'/clean.jpg');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->source);
        $this->deleteTemporaryEntries();

        parent::tearDown();
    }

    public function test_it_flags_the_watermarked_image_and_leaves_the_clean_one_unflagged(): void
    {
        $this->artisan('winsol:import-images', [
            'source' => $this->source,
            'folder' => 'testrange-flags',
        ])->assertExitCode(0);

        $container = AssetContainer::find('assets');

        $watermarked = $container->asset('testrange-flags/watermarked.jpg');
        $clean = $container->asset('testrange-flags/clean.jpg');

        $this->assertNotNull($watermarked, 'De watermerkfoto is niet geimporteerd');
        $this->assertNotNull($clean, 'De schone foto is niet geimporteerd');

        $this->assertTrue($watermarked->get('watermark'));
        $this->assertNotEmpty($watermarked->get('watermark_box'));

        $this->assertFalse($clean->get('watermark'));
    }

    public function test_it_compresses_the_imported_asset(): void
    {
        $this->artisan('winsol:import-images', [
            'source' => $this->source,
            'folder' => 'testrange-compression',
        ])->assertExitCode(0);

        $container = AssetContainer::find('assets');
        $watermarked = $container->asset('testrange-compression/watermarked.jpg');

        $stored = $watermarked->disk()->get($watermarked->path());
        $info = getimagesizefromstring($stored);

        $this->assertLessThanOrEqual(2500, $info[0]);
        $this->assertLessThan(
            filesize(base_path('tests/fixtures/images/watermarked.jpg')),
            strlen($stored),
        );
    }

    public function test_it_leaves_the_source_files_intact(): void
    {
        $this->artisan('winsol:import-images', ['source' => $this->source, 'folder' => 'testrange-source']);

        $this->assertFileExists($this->source.'/watermarked.jpg');
        $this->assertFileExists($this->source.'/clean.jpg');
    }

    public function test_it_skips_files_that_are_already_there(): void
    {
        $this->artisan('winsol:import-images', ['source' => $this->source, 'folder' => 'testrange-skip']);

        $this->artisan('winsol:import-images', ['source' => $this->source, 'folder' => 'testrange-skip'])
            ->expectsOutputToContain('2 overgeslagen')
            ->assertExitCode(0);

        $container = AssetContainer::find('assets');
        $paths = collect($container->assets('testrange-skip'))->map->path()->all();

        $this->assertCount(2, $paths, 'Een tweede import mag geen dubbele assets aanmaken');
    }
}
