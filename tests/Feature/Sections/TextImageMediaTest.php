<?php

namespace Tests\Feature\Sections;

use Illuminate\Support\Facades\Storage;
use Statamic\Contracts\Assets\Asset;
use Statamic\Facades\AssetContainer;

class TextImageMediaTest extends SectionTestCase
{
    /** Zelfde opzet als tests/Feature/Sections/RangeHeaderTest.php. */
    private function makeImageAsset(string $filename = 'pergolas.png'): Asset
    {
        Storage::fake('r2');

        $container = AssetContainer::make('assets')->disk('r2')->title('Assets');
        $container->save();

        $image = imagecreatetruecolor(1200, 1200);
        ob_start();
        imagepng($image);
        Storage::disk('r2')->put($filename, ob_get_clean());
        imagedestroy($image);

        $asset = $container->makeAsset($filename);
        $asset->save();

        return $asset;
    }

    public function test_it_renders_a_video_when_the_media_switch_is_video(): void
    {
        $html = $this->render('{{ partial:sections/textImage }}', [
            'title' => 'Pergola SO!',
            'media' => 'video',
            'video' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);

        $this->assertStringContainsString('<iframe', $html);
        $this->assertStringNotContainsString('<picture', $html);
    }

    public function test_it_renders_nothing_in_the_media_column_when_the_switch_is_none(): void
    {
        $html = $this->render('{{ partial:sections/textImage }}', [
            'title' => 'Pergola SO!',
            'media' => 'none',
            'image' => 'dummy-images/test-img-1.jpg',
        ]);

        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertStringNotContainsString('<picture', $html);
        $this->assertStringContainsString('Pergola SO!', $html);
    }

    /**
     * `media: video` met een leeg videoveld valt terug op de afbeelding — de
     * buitenste conditie (`media == 'video' and video`) sluit de videotak al
     * uit. De binnenste keuze mag die guard niet los van `media` herhalen,
     * anders rendert ze alsnog een lege `<video>` in plaats van de foto.
     */
    public function test_it_falls_back_to_the_image_when_media_is_video_but_the_video_field_is_empty(): void
    {
        config(['app.debug' => false]);

        $html = $this->render('{{ partial:sections/textImage }}', [
            'title' => 'Pergola SO!',
            'media' => 'video',
            'video' => '',
            'image' => $this->makeImageAsset(),
        ]);

        $this->assertStringNotContainsString('<video', $html);
        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertStringContainsString('<picture', $html);
    }
}
