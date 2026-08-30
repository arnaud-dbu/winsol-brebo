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

    /**
     * Klantuploads gaan als bijlage mee in de melding naar Winsol. Een
     * onbewerkte gsm-foto van 5 a 8 MB zou die mail over de limiet van de
     * verzenddienst duwen en hem laten bouncen — dan verdwijnt de aanvraag
     * stil. Vandaar dat `private` sinds kort óók gecomprimeerd wordt.
     */
    public function test_uploaded_jpeg_in_private_container_is_compressed(): void
    {
        Storage::fake('r2_private');

        $container = AssetContainer::find('private');

        $largeBytes = file_get_contents(base_path('tests/fixtures/images/large.jpg'));
        Storage::disk('r2_private')->put('herstellingen/photo.jpg', $largeBytes);

        $asset = $container->makeAsset('herstellingen/photo.jpg');
        $asset->save();

        AssetUploaded::dispatch($asset, 'photo.jpg');

        $stored = Storage::disk('r2_private')->get('herstellingen/photo.jpg');
        $this->assertLessThan(strlen($largeBytes), strlen($stored));

        $info = getimagesizefromstring($stored);
        $this->assertSame(2500, $info[0]);
    }

    /**
     * De filtering zelf, los van welke containers er op dit moment in de
     * config staan: alleen wat in `containers` genoemd wordt, wordt aangeraakt.
     * Beide bestaande containers staan er inmiddels in, dus die lijst wordt
     * hier tijdelijk teruggezet — anders test dit niets meer zodra er een
     * container bijkomt.
     */
    public function test_assets_in_containers_outside_the_config_are_untouched(): void
    {
        config(['image-compression.containers' => ['assets']]);

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
