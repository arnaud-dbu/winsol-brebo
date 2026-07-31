<?php

namespace Tests\Unit;

use App\Services\WatermarkDetector;
use App\Services\WatermarkResult;
use Tests\TestCase;

class WatermarkDetectorTest extends TestCase
{
    private function detect(string $fixture): WatermarkResult
    {
        return (new WatermarkDetector)->detect(
            file_get_contents(base_path("tests/fixtures/images/{$fixture}"))
        );
    }

    public function test_it_finds_the_winsol_watermark(): void
    {
        $result = $this->detect('watermarked.jpg');

        $this->assertTrue($result->hasWatermark);
        $this->assertGreaterThan(0.08, $result->cornerWhiteFraction);
    }

    public function test_it_leaves_a_clean_photo_alone(): void
    {
        $result = $this->detect('clean.jpg');

        $this->assertFalse($result->hasWatermark);
        $this->assertNull($result->box);
    }

    public function test_the_box_sits_in_the_bottom_right_quadrant(): void
    {
        $result = $this->detect('watermarked.jpg');
        [$width, $height] = getimagesize(base_path('tests/fixtures/images/watermarked.jpg'));

        $this->assertGreaterThan($width * 0.5, $result->box['x']);
        $this->assertGreaterThan($height * 0.5, $result->box['y']);
        $this->assertLessThan($height * 0.3, $result->box['height']);
    }

    public function test_it_reads_indexed_palette_images_correctly(): void
    {
        $result = (new WatermarkDetector)->detect($this->indexedImageWithWhiteCorner());

        $this->assertTrue($result->hasWatermark);
        $this->assertGreaterThan(0.08, $result->cornerWhiteFraction);
    }

    public function test_it_uses_the_control_zone_to_ignore_a_bright_horizon(): void
    {
        $result = (new WatermarkDetector)->detect($this->imageWithFullWidthWhiteBottomStrip());

        $this->assertGreaterThan(0.08, $result->cornerWhiteFraction);
        $this->assertFalse($result->hasWatermark);
        $this->assertNull($result->box);
    }

    public function test_it_returns_a_safe_result_for_empty_bytes(): void
    {
        $result = (new WatermarkDetector)->detect('');

        $this->assertFalse($result->hasWatermark);
        $this->assertNull($result->box);
    }

    public function test_it_returns_a_safe_result_for_non_image_bytes(): void
    {
        $result = (new WatermarkDetector)->detect('dit is geen afbeelding');

        $this->assertFalse($result->hasWatermark);
        $this->assertNull($result->box);
    }

    /**
     * Palette-based (indexed) PNG: `imagecreate()` levert een paletafbeelding op,
     * in tegenstelling tot `imagecreatetruecolor()`. De rechteronderhoek — dezelfde
     * zone als `WatermarkDetector::CORNER_X`/`CORNER_Y` — is wit, de rest zwart.
     */
    private function indexedImageWithWhiteCorner(): string
    {
        $width = 1000;
        $height = 1000;

        $image = imagecreate($width, $height);
        imagecolorallocate($image, 0, 0, 0);
        $white = imagecolorallocate($image, 255, 255, 255);

        imagefilledrectangle($image, (int) ($width * 0.74), (int) ($height * 0.845), $width - 1, $height - 1, $white);

        return $this->pngBytes($image);
    }

    /**
     * Truecolor afbeelding met een volle-breedte witte strook onderaan: zowel de
     * cornerzone als de controlezone linksonder worden wit, dus de ratio-check
     * hoort dit als een lichte lucht/gevel te herkennen in plaats van als watermerk.
     */
    private function imageWithFullWidthWhiteBottomStrip(): string
    {
        $width = 1000;
        $height = 1000;

        $image = imagecreatetruecolor($width, $height);
        $black = imagecolorallocate($image, 0, 0, 0);
        imagefill($image, 0, 0, $black);
        $white = imagecolorallocate($image, 255, 255, 255);

        imagefilledrectangle($image, 0, (int) ($height * 0.845), $width - 1, $height - 1, $white);

        return $this->pngBytes($image);
    }

    private function pngBytes(\GdImage $image): string
    {
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }
}
