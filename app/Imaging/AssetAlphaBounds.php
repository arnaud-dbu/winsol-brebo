<?php

namespace App\Imaging;

use Illuminate\Contracts\Cache\Repository;
use Statamic\Contracts\Assets\Asset;

/**
 * De alpha-omhullende van een asset, gecached.
 *
 * De `{{ img }}`-tag heeft deze maten nodig om de intrinsieke afmetingen en de
 * srcset-breedtes op het bijgesneden beeld te laten slaan in plaats van op het
 * canvas. Dat betekent het bronbestand van R2 halen en uitpakken, dus het
 * antwoord wordt gecached op id én wijzigingstijd: vervangt een redacteur het
 * beeld, dan verandert de sleutel vanzelf mee.
 */
final readonly class AssetAlphaBounds
{
    public function __construct(private Repository $cache) {}

    public function for(Asset $asset): ?AlphaBounds
    {
        if (! $asset->isImage() || strtolower($asset->extension()) !== 'png') {
            // Alleen png draagt een alpha-kanaal dat de moeite van een scan
            // waard is; jpeg en webp-uit-jpeg zijn per definitie dekkend.
            return null;
        }

        // Ook de bestandsgrootte in de sleutel: `lastModified` telt in hele
        // seconden, dus een vervangen beeld binnen dezelfde seconde zou anders
        // de maten van zijn voorganger houden.
        $key = implode('::', [
            'alpha-bounds',
            $asset->id(),
            $asset->lastModified()?->getTimestamp(),
            $asset->size(),
        ]);

        $cached = $this->cache->rememberForever($key, function () use ($asset) {
            $bounds = $this->scan($asset);

            // rememberForever slaat null niet op en zou dan elke render opnieuw
            // scannen. false is wél cachebaar en betekent hier "gescand, niets
            // bruikbaars gevonden".
            return $bounds ? [
                $bounds->left, $bounds->top,
                $bounds->width, $bounds->height,
                $bounds->canvasWidth, $bounds->canvasHeight,
            ] : false;
        });

        return $cached ? new AlphaBounds(...$cached) : null;
    }

    private function scan(Asset $asset): ?AlphaBounds
    {
        $image = @imagecreatefromstring($asset->contents());

        if ($image === false) {
            return null;
        }

        try {
            return AlphaBounds::scan($image);
        } finally {
            imagedestroy($image);
        }
    }
}
