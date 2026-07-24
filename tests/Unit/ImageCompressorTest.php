<?php

namespace Tests\Unit;

use App\Services\ImageCompressor;
use Tests\TestCase;

class ImageCompressorTest extends TestCase
{
    private function fixture(string $name): string
    {
        return file_get_contents(base_path("tests/fixtures/images/{$name}"));
    }

    public function test_jpeg_wider_than_max_width_is_resized(): void
    {
        $service = new ImageCompressor(maxWidth: 2500, jpegQuality: 85);

        $result = $service->compress(
            bytes: $this->fixture('large.jpg'),
            mime: 'image/jpeg',
            filename: 'photo.jpg',
        );

        $info = getimagesizefromstring($result->bytes);
        $this->assertSame(2500, $info[0], 'width should be capped at 2500');
        $this->assertLessThan(3000, $info[1], 'height should scale proportionally');
        $this->assertSame('image/jpeg', $result->mime);
        $this->assertSame('photo.jpg', $result->filename);
        $this->assertLessThan(strlen($this->fixture('large.jpg')), strlen($result->bytes));
    }

    public function test_jpeg_narrower_than_max_width_keeps_dimensions(): void
    {
        $service = new ImageCompressor(maxWidth: 2500, jpegQuality: 85);

        $result = $service->compress(
            bytes: $this->fixture('small.jpg'),
            mime: 'image/jpeg',
            filename: 'small.jpg',
        );

        $info = getimagesizefromstring($result->bytes);
        $this->assertSame(800, $info[0]);
        $this->assertSame(600, $info[1]);
        $this->assertSame('image/jpeg', $result->mime);
    }

    public function test_png_preserves_transparency_and_format(): void
    {
        $service = new ImageCompressor(maxWidth: 2500, jpegQuality: 85);

        $result = $service->compress(
            bytes: $this->fixture('alpha.png'),
            mime: 'image/png',
            filename: 'logo.png',
        );

        $this->assertSame('image/png', $result->mime);
        $this->assertSame('logo.png', $result->filename);

        $info = getimagesizefromstring($result->bytes);
        $this->assertSame(IMAGETYPE_PNG, $info[2]);

        $tmp = tempnam(sys_get_temp_dir(), 'png');
        file_put_contents($tmp, $result->bytes);
        $img = imagecreatefrompng($tmp);
        $colorIndex = imagecolorat($img, 0, 0);
        $rgba = imagecolorsforindex($img, $colorIndex);
        $this->assertGreaterThan(0, $rgba['alpha'], 'corner pixel should remain transparent');
        imagedestroy($img);
        unlink($tmp);
    }

    public function test_heic_returns_unchanged_when_imagick_unavailable(): void
    {
        if (extension_loaded('imagick')) {
            $this->markTestSkipped('Imagick is available; this path tests degradation.');
        }

        $service = new ImageCompressor(maxWidth: 2500, jpegQuality: 85);

        $bytes = 'fake-heic-bytes';
        $result = $service->compress(
            bytes: $bytes,
            mime: 'image/heic',
            filename: 'photo.heic',
        );

        $this->assertSame($bytes, $result->bytes);
        $this->assertSame('image/heic', $result->mime);
        $this->assertSame('photo.heic', $result->filename);
    }

    public function test_corrupt_jpeg_throws(): void
    {
        $service = new ImageCompressor(maxWidth: 2500, jpegQuality: 85);

        $this->expectException(\Throwable::class);

        $service->compress(
            bytes: $this->fixture('corrupt.jpg'),
            mime: 'image/jpeg',
            filename: 'broken.jpg',
        );
    }

    public function test_unsupported_mime_is_passthrough(): void
    {
        $service = new ImageCompressor(maxWidth: 2500, jpegQuality: 85);

        $bytes = 'GIF89a fake bytes';
        $result = $service->compress(
            bytes: $bytes,
            mime: 'image/gif',
            filename: 'anim.gif',
        );

        $this->assertSame($bytes, $result->bytes);
        $this->assertSame('image/gif', $result->mime);
        $this->assertSame('anim.gif', $result->filename);
    }
}
