<?php

namespace App\Imaging\Manipulators;

use App\Imaging\AlphaBounds;
use GdImage;
use Intervention\Image\Interfaces\ImageInterface;
use League\Glide\Manipulators\BaseManipulator;

/**
 * Snijdt de transparante rand van een beeld weg: `trim=1`.
 *
 * Intervention heeft zelf een `trim()`, maar die is hier onbruikbaar. De
 * GD-implementatie leidt de weg te snijden kleur af uit het gemiddelde van de
 * vier hoeken en vergelijkt op RGB, zónder naar alpha te kijken. Bij een
 * transparant canvas is dat gemiddelde zwart, en dan eet hij een zwart product
 * — de airco — voor een deel op. Vandaar een eigen, alpha-gestuurde variant.
 *
 * Deze manipulator hangt vóór `Size` in de keten, zodat `w` op het bijgesneden
 * beeld slaat en niet op het canvas.
 */
class TrimTransparent extends BaseManipulator
{
    public function getApiParams(): array
    {
        // Glide filtert zowel de manipulatie als het cachepad op deze lijst
        // (League\Glide\Server::getAllParams). Staat `trim` er niet in, dan
        // valt de parameter stil weg én delen bijgesneden en niet-bijgesneden
        // varianten hetzelfde cachepad.
        return ['trim'];
    }

    public function run(ImageInterface $image): ImageInterface
    {
        if (! filter_var($this->getParam('trim'), FILTER_VALIDATE_BOOL)) {
            return $image;
        }

        $native = $image->core()->native();

        // De alpha-scan leest GD rechtstreeks uit. Bij een andere Glide-driver
        // blijft het beeld ongemoeid in plaats van stilletjes verkeerd —
        // `statamic.assets.image_manipulation.driver` staat op `gd`.
        if (! $native instanceof GdImage) {
            return $image;
        }

        $bounds = AlphaBounds::scan($native);

        if (! $bounds || $bounds->coversWholeCanvas()) {
            return $image;
        }

        return $image->crop(
            width: $bounds->width,
            height: $bounds->height,
            offset_x: $bounds->left,
            offset_y: $bounds->top,
        );
    }
}
