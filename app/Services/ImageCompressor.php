<?php

namespace App\Services;

use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;
use Throwable;

class ImageCompressor
{
    public function __construct(
        private readonly int $maxWidth = 2500,
        private readonly int $jpegQuality = 85,
    ) {}

    public function compress(string $bytes, string $mime, string $filename): CompressionResult
    {
        return match ($mime) {
            'image/jpeg' => $this->compressJpeg($bytes, $filename),
            'image/png' => $this->compressPng($bytes, $filename),
            'image/heic', 'image/heif' => $this->compressHeic($bytes, $filename),
            default => new CompressionResult($bytes, $mime, $filename),
        };
    }

    private function compressJpeg(string $bytes, string $filename): CompressionResult
    {
        $manager = new ImageManager(new GdDriver());
        $image = $manager->read($bytes);

        if ($image->width() > $this->maxWidth) {
            $image->scaleDown(width: $this->maxWidth);
        }

        $encoded = (string) $image->toJpeg(quality: $this->jpegQuality);

        return new CompressionResult($encoded, 'image/jpeg', $filename);
    }

    private function compressPng(string $bytes, string $filename): CompressionResult
    {
        $manager = new ImageManager(new GdDriver());
        $image = $manager->read($bytes);

        if ($image->width() > $this->maxWidth) {
            $image->scaleDown(width: $this->maxWidth);
        }

        $encoded = (string) $image->toPng();

        return new CompressionResult($encoded, 'image/png', $filename);
    }

    private function compressHeic(string $bytes, string $filename): CompressionResult
    {
        if (! extension_loaded('imagick')) {
            return new CompressionResult($bytes, 'image/heic', $filename);
        }

        try {
            $imagick = new \Imagick();
            $imagick->readImageBlob($bytes);
            $imagick->setImageFormat('jpeg');

            if ($imagick->getImageWidth() > $this->maxWidth) {
                $imagick->scaleImage($this->maxWidth, 0);
            }

            $imagick->setImageCompressionQuality($this->jpegQuality);
            $jpegBytes = $imagick->getImageBlob();
            $imagick->clear();

            $newName = preg_replace('/\.(heic|heif)$/i', '.jpg', $filename);

            return new CompressionResult($jpegBytes, 'image/jpeg', $newName);
        } catch (Throwable) {
            return new CompressionResult($bytes, 'image/heic', $filename);
        }
    }
}
