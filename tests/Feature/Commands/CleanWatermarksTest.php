<?php

namespace Tests\Feature\Commands;

use Illuminate\Support\Facades\File;
use Statamic\Assets\Asset;
use Statamic\Facades\AssetContainer;
use Tests\Concerns\CreatesTemporaryContent;
use Tests\TestCase;

class CleanWatermarksTest extends TestCase
{
    use CreatesTemporaryContent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeAssetDisk();
    }

    protected function tearDown(): void
    {
        $this->deleteTemporaryEntries();

        parent::tearDown();
    }

    private function importFixture(string $fixture, string $name): void
    {
        $dir = storage_path('framework/testing/clean-source');
        File::ensureDirectoryExists($dir);
        File::copy(base_path("tests/fixtures/images/{$fixture}"), "{$dir}/{$name}");

        $this->artisan('winsol:import-images', ['source' => $dir, 'folder' => 'testrange']);

        File::deleteDirectory($dir);
    }

    /**
     * Statamic's Asset kent geen fresh(); opnieuw ophalen gaat via de container.
     */
    private function asset(string $path): Asset
    {
        return AssetContainer::find('assets')->asset($path);
    }

    private function useInEntry(string $path): void
    {
        $this->temporaryEntry('products', 'testproduct', [
            'title' => 'Testproduct',
            'image' => $path,
        ]);
    }

    public function test_it_only_touches_watermarked_assets_that_an_entry_uses(): void
    {
        $this->importFixture('watermarked.jpg', 'used.jpg');
        $this->importFixture('watermarked.jpg', 'unused.jpg');
        $this->useInEntry('testrange/used.jpg');

        $heightBefore = $this->asset('testrange/used.jpg')->height();

        $this->artisan('winsol:clean-watermarks')
            ->expectsOutputToContain('1 bijgesneden')
            ->assertExitCode(0);

        $this->assertLessThan($heightBefore, $this->asset('testrange/used.jpg')->height(), 'De gebruikte foto is niet bijgesneden');
        $this->assertFalse($this->asset('testrange/used.jpg')->get('watermark'), 'De vlag is niet omgezet');

        $this->assertTrue($this->asset('testrange/unused.jpg')->get('watermark'), 'De ongebruikte foto had ongemoeid moeten blijven');
    }

    public function test_dry_run_changes_nothing(): void
    {
        $this->importFixture('watermarked.jpg', 'used.jpg');
        $this->useInEntry('testrange/used.jpg');

        $heightBefore = $this->asset('testrange/used.jpg')->height();

        $this->artisan('winsol:clean-watermarks', ['--dry-run' => true])->assertExitCode(0);

        $this->assertSame($heightBefore, $this->asset('testrange/used.jpg')->height());
        $this->assertTrue($this->asset('testrange/used.jpg')->get('watermark'));
    }

    public function test_list_prints_the_filenames_without_changing_anything(): void
    {
        $this->importFixture('watermarked.jpg', 'used.jpg');
        $this->useInEntry('testrange/used.jpg');

        $this->artisan('winsol:clean-watermarks', ['--list' => true])
            ->expectsOutputToContain('testrange/used.jpg')
            ->assertExitCode(0);

        $this->assertTrue($this->asset('testrange/used.jpg')->get('watermark'));
    }
}
