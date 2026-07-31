<?php

namespace App\Services;

readonly class WatermarkResult
{
    /**
     * @param  array{x: int, y: int, width: int, height: int}|null  $box
     */
    public function __construct(
        public bool $hasWatermark,
        public float $cornerWhiteFraction,
        public ?array $box = null,
    ) {}
}
