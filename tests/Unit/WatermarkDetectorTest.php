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
}
