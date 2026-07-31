<?php

namespace App\Services;

class WatermarkDetector
{
    /**
     * Het Winsol-woordmerk staat wit en op een vaste plek rechtsonder. De
     * witfractie daar wordt afgezet tegen dezelfde zone linksonder: dat vangt
     * foto's op met een lichte lucht of witte gevel in die hoek, die anders
     * vals positief zouden zijn.
     */
    private const CORNER_X = 0.74;

    private const CORNER_Y = 0.845;

    private const WHITE_THRESHOLD = 238;

    private const MIN_FRACTION = 0.08;

    private const MIN_RATIO = 4.0;

    public function detect(string $bytes): WatermarkResult
    {
        $image = @imagecreatefromstring($bytes);

        if ($image === false) {
            return new WatermarkResult(false, 0.0);
        }

        try {
            if (! imageistruecolor($image)) {
                imagepalettetotruecolor($image);
            }

            $width = imagesx($image);
            $height = imagesy($image);

            $cornerX = (int) ($width * self::CORNER_X);
            $cornerY = (int) ($height * self::CORNER_Y);
            $controlWidth = $width - $cornerX;

            $corner = $this->whitePixels($image, $cornerX, $cornerY, $width, $height);
            $control = $this->whitePixels($image, 0, $cornerY, $controlWidth, $height);

            $area = max(1, ($width - $cornerX) * ($height - $cornerY));
            $cornerFraction = $corner['count'] / $area;
            $controlFraction = $control['count'] / $area;

            $has = $cornerFraction >= self::MIN_FRACTION
                && $cornerFraction >= self::MIN_RATIO * $controlFraction;

            return new WatermarkResult(
                hasWatermark: $has,
                cornerWhiteFraction: $cornerFraction,
                box: $has ? $corner['box'] : null,
            );
        } finally {
            imagedestroy($image);
        }
    }

    /**
     * @return array{count: int, box: array{x: int, y: int, width: int, height: int}}
     */
    private function whitePixels(\GdImage $image, int $fromX, int $fromY, int $toX, int $toY): array
    {
        $count = 0;
        $minX = $toX;
        $minY = $toY;
        $maxX = $fromX;
        $maxY = $fromY;

        for ($y = $fromY; $y < $toY; $y++) {
            for ($x = $fromX; $x < $toX; $x++) {
                $rgb = imagecolorat($image, $x, $y);
                $luma = (int) (
                    0.299 * (($rgb >> 16) & 0xFF)
                    + 0.587 * (($rgb >> 8) & 0xFF)
                    + 0.114 * ($rgb & 0xFF)
                );

                if ($luma <= self::WHITE_THRESHOLD) {
                    continue;
                }

                $count++;
                $minX = min($minX, $x);
                $minY = min($minY, $y);
                $maxX = max($maxX, $x);
                $maxY = max($maxY, $y);
            }
        }

        return [
            'count' => $count,
            'box' => [
                'x' => $minX,
                'y' => $minY,
                'width' => max(0, $maxX - $minX),
                'height' => max(0, $maxY - $minY),
            ],
        ];
    }
}
