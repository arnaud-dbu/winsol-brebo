<?php

namespace App\Services;

final readonly class CompressionResult
{
    public function __construct(
        public string $bytes,
        public string $mime,
        public string $filename,
    ) {}
}
