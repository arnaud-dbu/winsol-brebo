<?php

namespace Tests\Feature\Commands;

use App\Services\WatermarkDetector;
use Illuminate\Http\UploadedFile;
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

    private function importFixture(string $fixture, string $name): void
    {
        $dir = storage_path('framework/testing/clean-source');
        File::ensureDirectoryExists($dir);
        File::copy(base_path("tests/fixtures/images/{$fixture}"), "{$dir}/{$name}");

        $this->artisan('winsol:import-images', ['source' => $dir, 'folder' => 'testrange']);

        File::deleteDirectory($dir);
    }

    /**
     * Bootst winsol:import-images na voor extensies die het commando zelf
     * niet importeert (het beperkt zijn Finder-pattern tot jpe?g|png), zodat
     * ook een webp-asset met watermerkdata opgezet kan worden voor de
     * formaatbehoud-test.
     */
    private function importBytes(string $bytes, string $path): Asset
    {
        $container = AssetContainer::find('assets');

        $tempPath = sys_get_temp_dir().'/winsol-test-'.bin2hex(random_bytes(8)).'.'.pathinfo($path, PATHINFO_EXTENSION);
        file_put_contents($tempPath, $bytes);

        $asset = $container->makeAsset($path)->upload(
            new UploadedFile($tempPath, basename($path), null, null, true)
        );

        if (is_file($tempPath)) {
            unlink($tempPath);
        }

        $result = app(WatermarkDetector::class)->detect($asset->disk()->get($asset->path()));

        $asset->set('watermark', $result->hasWatermark);
        $asset->set('watermark_box', $result->box
            ? implode(',', [$result->box['x'], $result->box['y'], $result->box['width'], $result->box['height']])
            : '');
        $asset->save();

        return $asset;
    }

    /**
     * Statamic's Asset kent geen fresh(); opnieuw ophalen gaat via de container.
     */
    private function asset(string $path): Asset
    {
        return AssetContainer::find('assets')->asset($path);
    }

    private function assetBytes(string $path): string
    {
        return $this->asset($path)->disk()->get($path);
    }

    private function useInEntry(string $path, string $slug = 'testproduct'): void
    {
        $this->temporaryEntry('products', $slug, [
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
        $unusedBytesBefore = $this->assetBytes('testrange/unused.jpg');

        $this->artisan('winsol:clean-watermarks', ['--force' => true])
            ->expectsOutputToContain('1 bijgesneden')
            ->assertExitCode(0);

        $this->assertLessThan($heightBefore, $this->asset('testrange/used.jpg')->height(), 'De gebruikte foto is niet bijgesneden');
        $this->assertFalse($this->asset('testrange/used.jpg')->get('watermark'), 'De vlag is niet omgezet');

        $this->assertTrue($this->asset('testrange/unused.jpg')->get('watermark'), 'De ongebruikte foto had ongemoeid moeten blijven');
        $this->assertSame($unusedBytesBefore, $this->assetBytes('testrange/unused.jpg'), 'De ongebruikte foto is byte voor byte gewijzigd');
    }

    public function test_declining_the_confirmation_changes_nothing(): void
    {
        $this->importFixture('watermarked.jpg', 'used.jpg');
        $this->useInEntry('testrange/used.jpg');

        $bytesBefore = $this->assetBytes('testrange/used.jpg');

        $this->artisan('winsol:clean-watermarks')
            ->expectsConfirmation('Doorgaan?', 'no')
            ->assertExitCode(0);

        $this->assertSame($bytesBefore, $this->assetBytes('testrange/used.jpg'));
        $this->assertTrue($this->asset('testrange/used.jpg')->get('watermark'));
    }

    public function test_non_interactive_mode_without_force_aborts(): void
    {
        $this->importFixture('watermarked.jpg', 'used.jpg');
        $this->useInEntry('testrange/used.jpg');

        $bytesBefore = $this->assetBytes('testrange/used.jpg');

        $this->artisan('winsol:clean-watermarks', ['--no-interaction' => true])
            ->expectsOutputToContain('Niet-interactieve modus')
            ->assertExitCode(1);

        $this->assertSame($bytesBefore, $this->assetBytes('testrange/used.jpg'));
        $this->assertTrue($this->asset('testrange/used.jpg')->get('watermark'));
    }

    public function test_dry_run_changes_nothing(): void
    {
        $this->importFixture('watermarked.jpg', 'used.jpg');
        $this->useInEntry('testrange/used.jpg');

        $heightBefore = $this->asset('testrange/used.jpg')->height();
        $bytesBefore = $this->assetBytes('testrange/used.jpg');
        $metaBefore = $this->asset('testrange/used.jpg')->meta();

        $this->artisan('winsol:clean-watermarks', ['--dry-run' => true])->assertExitCode(0);

        $this->assertSame($heightBefore, $this->asset('testrange/used.jpg')->height());
        $this->assertTrue($this->asset('testrange/used.jpg')->get('watermark'));
        $this->assertSame($bytesBefore, $this->assetBytes('testrange/used.jpg'), 'dry-run heeft de foto byte voor byte gewijzigd');
        $this->assertSame($metaBefore, $this->asset('testrange/used.jpg')->meta(), 'dry-run heeft de meta-yaml gewijzigd');
    }

    public function test_list_prints_the_filenames_without_changing_anything(): void
    {
        $this->importFixture('watermarked.jpg', 'used.jpg');
        $this->useInEntry('testrange/used.jpg');

        $bytesBefore = $this->assetBytes('testrange/used.jpg');
        $metaBefore = $this->asset('testrange/used.jpg')->meta();

        $this->artisan('winsol:clean-watermarks', ['--list' => true])
            ->expectsOutputToContain('used.jpg')
            ->assertExitCode(0);

        $this->assertTrue($this->asset('testrange/used.jpg')->get('watermark'));
        $this->assertSame($bytesBefore, $this->assetBytes('testrange/used.jpg'), '--list heeft de foto byte voor byte gewijzigd');
        $this->assertSame($metaBefore, $this->asset('testrange/used.jpg')->meta(), '--list heeft de meta-yaml gewijzigd');
    }

    /**
     * De lijst is een aanvraag bij Winsol, die zijn foto's onder de
     * oorspronkelijke naam kent. Het opgeslagen pad is dat niet: de sanering
     * bij import strijkt accenten en spaties glad en is niet omkeerbaar.
     */
    public function test_list_prints_the_original_filename_and_not_the_sanitized_path(): void
    {
        // ASCII, want macOS bewaart een bestandsnaam met accenten gedecomponeerd
        // (NFD) en dan hangt het gesaneerde pad van het besturingssysteem af.
        // De sanering die telt — hoofdletters, spaties, leestekens — is hier
        // evengoed zichtbaar.
        $original = 'Winsol_2019_Mol_Pergola SO! (23).jpg';
        $this->importFixture('watermarked.jpg', $original);
        $this->useInEntry('testrange/winsol_2019_mol_pergola-so!-(23).jpg');

        $this->assertNotNull($this->asset('testrange/winsol_2019_mol_pergola-so!-(23).jpg'), 'De import saneerde het pad anders dan verwacht');

        $this->artisan('winsol:clean-watermarks', ['--list' => true])
            ->expectsOutputToContain($original)
            ->assertExitCode(0);
    }

    public function test_list_falls_back_to_the_path_for_an_asset_without_a_source_filename(): void
    {
        $this->importFixture('watermarked.jpg', 'used.jpg');
        $this->useInEntry('testrange/used.jpg');

        $asset = $this->asset('testrange/used.jpg');
        $asset->remove('source_filename');
        $asset->save();

        $this->artisan('winsol:clean-watermarks', ['--list' => true])
            ->expectsOutputToContain('testrange/used.jpg')
            ->assertExitCode(0);
    }

    public function test_second_run_does_not_crop_again(): void
    {
        $this->importFixture('watermarked.jpg', 'used.jpg');
        $this->useInEntry('testrange/used.jpg');

        $this->artisan('winsol:clean-watermarks', ['--force' => true])->assertExitCode(0);

        $heightAfterFirstRun = $this->asset('testrange/used.jpg')->height();
        $bytesAfterFirstRun = $this->assetBytes('testrange/used.jpg');

        $this->artisan('winsol:clean-watermarks', ['--force' => true])
            ->expectsOutputToContain('0 bijgesneden')
            ->assertExitCode(0);

        $this->assertSame($heightAfterFirstRun, $this->asset('testrange/used.jpg')->height(), 'Tweede run heeft opnieuw bijgesneden');
        $this->assertSame($bytesAfterFirstRun, $this->assetBytes('testrange/used.jpg'), 'Tweede run heeft de bytes herschreven');
    }

    public function test_a_garbage_watermark_box_is_skipped_instead_of_reduced_to_one_pixel(): void
    {
        $this->importFixture('watermarked.jpg', 'used.jpg');
        $this->useInEntry('testrange/used.jpg');

        $asset = $this->asset('testrange/used.jpg');
        $asset->set('watermark_box', 'abc,def,ghi,jkl');
        $asset->save();

        $heightBefore = $this->asset('testrange/used.jpg')->height();
        $bytesBefore = $this->assetBytes('testrange/used.jpg');

        $this->artisan('winsol:clean-watermarks', ['--force' => true])
            ->expectsOutputToContain('Geen bruikbaar watermerkvlak')
            ->expectsOutputToContain('0 bijgesneden')
            ->assertExitCode(0);

        $this->assertSame($heightBefore, $this->asset('testrange/used.jpg')->height());
        $this->assertSame($bytesBefore, $this->assetBytes('testrange/used.jpg'));
        $this->assertTrue($this->asset('testrange/used.jpg')->get('watermark'), 'Een overgeslagen foto moet watermerkt blijven voor een latere run');
    }

    public function test_a_watermark_box_near_the_top_of_the_image_is_skipped(): void
    {
        $this->importFixture('watermarked.jpg', 'used.jpg');
        $this->useInEntry('testrange/used.jpg');

        $asset = $this->asset('testrange/used.jpg');
        $asset->set('watermark_box', '0,0,0,0');
        $asset->save();

        $heightBefore = $this->asset('testrange/used.jpg')->height();
        $bytesBefore = $this->assetBytes('testrange/used.jpg');

        $this->artisan('winsol:clean-watermarks', ['--force' => true])
            ->expectsOutputToContain('Onwaarschijnlijk watermerkvlak')
            ->assertExitCode(0);

        $this->assertSame($heightBefore, $this->asset('testrange/used.jpg')->height());
        $this->assertSame($bytesBefore, $this->assetBytes('testrange/used.jpg'));
        $this->assertTrue($this->asset('testrange/used.jpg')->get('watermark'));
    }

    public function test_a_watermark_box_slightly_beyond_the_image_height_is_clamped_to_a_pinned_height(): void
    {
        $this->importFixture('watermarked.jpg', 'used.jpg');
        $this->useInEntry('testrange/used.jpg');

        $asset = $this->asset('testrange/used.jpg');
        $heightBefore = $asset->height();
        // y = hoogte + 2 ligt net voorbij de afbeelding; na de marge van 4px
        // resulteert dat in een kleine, echte snede van 2px in plaats van een
        // no-op. Een concrete verwachte hoogte (i.p.v. enkel <=) pint vast
        // dat de begrenzing niet naar een veel te agressieve crop afglijdt.
        $asset->set('watermark_box', '0,'.($heightBefore + 2).',10,10');
        $asset->save();

        $this->artisan('winsol:clean-watermarks', ['--force' => true])
            ->expectsOutputToContain('1 bijgesneden')
            ->assertExitCode(0);

        $this->assertSame($heightBefore - 2, $this->asset('testrange/used.jpg')->height());
        $this->assertFalse($this->asset('testrange/used.jpg')->get('watermark'));
    }

    public function test_a_watermark_box_far_beyond_the_image_height_is_skipped_as_stale(): void
    {
        $this->importFixture('watermarked.jpg', 'used.jpg');
        $this->useInEntry('testrange/used.jpg');

        $asset = $this->asset('testrange/used.jpg');
        $heightBefore = $asset->height();
        // Ver voorbij de hoogte, ruim buiten de marge: na begrenzing op de
        // werkelijke hoogte zou er niets meer afgesneden worden. Dat is niet
        // per se onzin (het kan een box zijn die na een eerdere, mislukte
        // run al niet meer bij dit bestand hoort), dus die wordt overgeslagen
        // in plaats van als "1 bijgesneden" schoon geboekt terwijl de foto
        // ongewijzigd bleef.
        $asset->set('watermark_box', '0,'.($heightBefore + 500).',10,10');
        $asset->save();

        $bytesBefore = $this->assetBytes('testrange/used.jpg');

        $this->artisan('winsol:clean-watermarks', ['--force' => true])
            ->expectsOutputToContain('Verouderd of onbruikbaar watermerkvlak')
            ->expectsOutputToContain('0 bijgesneden')
            ->assertExitCode(0);

        $this->assertSame($heightBefore, $this->asset('testrange/used.jpg')->height());
        $this->assertSame($bytesBefore, $this->assetBytes('testrange/used.jpg'));
        $this->assertTrue($this->asset('testrange/used.jpg')->get('watermark'), 'Een overgeslagen foto moet watermerkt blijven, anders is ze niet meer vindbaar voor een correcte box');
    }

    public function test_one_unreadable_asset_does_not_stop_the_rest_of_the_run(): void
    {
        $this->importFixture('watermarked.jpg', 'broken.jpg');
        $this->importFixture('watermarked.jpg', 'used.jpg');

        // De bytes worden pas ná de import gecorrumpeerd: winsol:import-images
        // zelf triggert Statamic's eigen preset-generatie op AssetSaved, die
        // op werkelijk kapotte brondata zou crashen — los van het commando
        // dat we hier toetsen. Rechtstreeks op de disk schrijven omzeilt dat
        // en simuleert een bestand dat pas onleesbaar wordt op het moment dat
        // winsol:clean-watermarks het leest.
        $broken = $this->asset('testrange/broken.jpg');
        $broken->disk()->put($broken->path(), 'niet langer een geldige afbeelding');

        $this->useInEntry('testrange/broken.jpg', 'broken-product');
        $this->useInEntry('testrange/used.jpg', 'used-product');

        $heightBefore = $this->asset('testrange/used.jpg')->height();

        $this->artisan('winsol:clean-watermarks', ['--force' => true])
            ->expectsOutputToContain('Fout bij testrange/broken.jpg, overgeslagen')
            ->expectsOutputToContain('1 bijgesneden')
            ->assertExitCode(0);

        $this->assertLessThan($heightBefore, $this->asset('testrange/used.jpg')->height(), 'De leesbare foto had ondanks de kapotte buur nog bijgesneden moeten worden');
        $this->assertFalse($this->asset('testrange/used.jpg')->get('watermark'));
        $this->assertTrue($this->asset('testrange/broken.jpg')->get('watermark'), 'De kapotte foto moet watermerkt blijven voor handmatige controle');
    }

    public function test_format_is_preserved_for_png(): void
    {
        $sourceBytes = File::get(base_path('tests/fixtures/images/watermarked.jpg'));
        $image = imagecreatefromstring($sourceBytes);
        ob_start();
        imagepng($image);
        $pngBytes = ob_get_clean();
        imagedestroy($image);

        $this->importBytes($pngBytes, 'testrange/used.png');
        $this->useInEntry('testrange/used.png');

        $this->assertTrue($this->asset('testrange/used.png')->get('watermark'), 'De gegenereerde png-fixture bevat geen gedetecteerd watermerk');

        $this->artisan('winsol:clean-watermarks', ['--force' => true])
            ->expectsOutputToContain('1 bijgesneden')
            ->assertExitCode(0);

        $croppedBytes = $this->assetBytes('testrange/used.png');
        $this->assertSame("\x89PNG\r\n\x1a\n", substr($croppedBytes, 0, 8), 'De bijgesneden png is niet langer een geldige png');
        $this->assertFalse($this->asset('testrange/used.png')->get('watermark'));
    }

    public function test_format_is_preserved_for_webp(): void
    {
        $sourceBytes = File::get(base_path('tests/fixtures/images/watermarked.jpg'));
        $image = imagecreatefromstring($sourceBytes);
        ob_start();
        imagewebp($image);
        $webpBytes = ob_get_clean();
        imagedestroy($image);

        $asset = $this->importBytes($webpBytes, 'testrange/used.webp');
        $this->useInEntry('testrange/used.webp');

        $this->assertTrue($asset->get('watermark'), 'De gegenereerde webp-fixture bevat geen gedetecteerd watermerk');

        $this->artisan('winsol:clean-watermarks', ['--force' => true])
            ->expectsOutputToContain('1 bijgesneden')
            ->assertExitCode(0);

        $croppedBytes = $this->assetBytes('testrange/used.webp');
        $this->assertSame('RIFF', substr($croppedBytes, 0, 4), 'De bijgesneden webp mist de RIFF-header');
        $this->assertSame('WEBP', substr($croppedBytes, 8, 4), 'De bijgesneden webp is niet langer een geldige webp');
        $this->assertFalse($this->asset('testrange/used.webp')->get('watermark'));
    }

    public function test_png_transparency_survives_the_crop(): void
    {
        $pngBytes = File::get(base_path('tests/fixtures/images/alpha.png'));
        $sourceImage = imagecreatefromstring($pngBytes);
        $rgbaBefore = imagecolorat($sourceImage, 10, 10);
        $alphaBefore = ($rgbaBefore >> 24) & 0xFF;
        $height = imagesy($sourceImage);
        imagedestroy($sourceImage);

        $asset = $this->importBytes($pngBytes, 'testrange/alpha.png');
        $this->useInEntry('testrange/alpha.png');

        // alpha.png bevat geen echt watermerk; de vlag en box worden hier
        // opgelegd om enkel het formaatbehoud van de crop te toetsen, los
        // van WatermarkDetector.
        $asset->set('watermark', true);
        $asset->set('watermark_box', '0,'.($height - 10).',0,0');
        $asset->save();

        $this->artisan('winsol:clean-watermarks', ['--force' => true])
            ->expectsOutputToContain('1 bijgesneden')
            ->assertExitCode(0);

        $croppedBytes = $this->assetBytes('testrange/alpha.png');
        $croppedImage = imagecreatefromstring($croppedBytes);
        $rgbaAfter = imagecolorat($croppedImage, 10, 10);
        $alphaAfter = ($rgbaAfter >> 24) & 0xFF;
        imagedestroy($croppedImage);

        $this->assertSame($alphaBefore, $alphaAfter, 'Transparantie is niet behouden na het bijsnijden');
    }
}
