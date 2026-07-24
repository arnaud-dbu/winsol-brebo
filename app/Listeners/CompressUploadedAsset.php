<?php

namespace App\Listeners;

use App\Services\ImageCompressor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Statamic\Assets\Asset;
use Statamic\Events\AssetUploaded;
use Throwable;

class CompressUploadedAsset
{
    public function __construct(
        private readonly ImageCompressor $compressor,
    ) {}

    public function handle(AssetUploaded $event): void
    {
        if (! config('image-compression.enabled')) {
            return;
        }

        $asset = $event->asset;

        if (! $asset instanceof Asset) {
            return;
        }

        $containerHandle = $asset->container()->handle();
        if (! in_array($containerHandle, (array) config('image-compression.containers'), true)) {
            return;
        }

        $mime = $asset->mimeType();
        if (! in_array($mime, (array) config('image-compression.process_mimes'), true)) {
            return;
        }

        try {
            $bytes = $asset->disk()->get($asset->path());
            if ($bytes === null) {
                return;
            }

            $result = $this->compressor->compress(
                bytes: $bytes,
                mime: $mime,
                filename: $asset->basename(),
            );

            $asset->disk()->put($asset->path(), $result->bytes);

            if ($result->filename !== $asset->basename()) {
                $newPath = dirname($asset->path()).'/'.$result->filename;
                $asset->disk()->move($asset->path(), $newPath);
                $asset->path($newPath)->save();
            }

            Cache::forget($asset->metaCacheKey());
            $asset->writeMeta($asset->generateMeta());
        } catch (Throwable $e) {
            Log::warning('Asset compression failed; original kept.', [
                'asset' => $asset->path(),
                'mime' => $mime,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
