<?php

namespace App\Imaging;

use GdImage;

/**
 * De rechthoek waarbinnen een afbeelding niet-transparant is.
 *
 * De range-png's zijn allemaal een vierkant canvas met het product gecentreerd
 * en transparante lucht eromheen. Hoeveel lucht verschilt sterk per beeld — van
 * 5% tot 28% aan de kant die naar de tekst kijkt — waardoor een box die op het
 * cánvas uitlijnt het product per range ergens anders neerzet. Deze klasse
 * levert de maten om op het product zelf uit te lijnen.
 */
final readonly class AlphaBounds
{
    public function __construct(
        public int $left,
        public int $top,
        public int $width,
        public int $height,
        public int $canvasWidth,
        public int $canvasHeight,
    ) {}

    /**
     * Alpha loopt in GD van 0 (dekkend) tot 127 (volledig transparant). De
     * grens ligt bewust hoog: alles wat ook maar een beetje zichtbaar is telt
     * mee, zodat een zachte schaduwrand of antialiasing niet wordt afgesneden.
     */
    private const OPAQUE_BELOW = 120;

    /**
     * Boven dit aantal monsters per as wordt er overgeslagen. Een bron van
     * 2500px (de `max_upload`-preset) zou anders 6,25M pixels kosten bij een
     * scan die op elke aanvraag draait zolang `GLIDE_CACHE` leeg is.
     */
    private const MAX_SAMPLES = 500;

    /**
     * Scan het alpha-kanaal en geef de omhullende rechthoek terug.
     *
     * De scan bemonstert met een stap in plaats van elke pixel. De gevonden
     * rand wordt daarna met die stap naar buiten opgerekt, zodat het resultaat
     * eerder iets te ruim dan te krap is: te ruim laat wat lucht staan, te krap
     * snijdt het product aan.
     *
     * Geeft `null` terug als er geen enkele zichtbare pixel is — een volledig
     * transparant beeld heeft geen zinnige omhullende.
     */
    public static function scan(GdImage $image): ?self
    {
        $width = imagesx($image);
        $height = imagesy($image);

        $step = max(1, (int) ceil(max($width, $height) / self::MAX_SAMPLES));

        $minX = $width;
        $minY = $height;
        $maxX = -1;
        $maxY = -1;

        for ($y = 0; $y < $height; $y += $step) {
            for ($x = 0; $x < $width; $x += $step) {
                if (((imagecolorat($image, $x, $y) >> 24) & 0x7F) >= self::OPAQUE_BELOW) {
                    continue;
                }

                if ($x < $minX) {
                    $minX = $x;
                }
                if ($x > $maxX) {
                    $maxX = $x;
                }
                if ($y < $minY) {
                    $minY = $y;
                }
                if ($y > $maxY) {
                    $maxY = $y;
                }
            }
        }

        if ($maxX < 0) {
            return null;
        }

        $left = max(0, $minX - $step);
        $top = max(0, $minY - $step);
        $right = min($width - 1, $maxX + $step);
        $bottom = min($height - 1, $maxY + $step);

        return new self(
            left: $left,
            top: $top,
            width: $right - $left + 1,
            height: $bottom - $top + 1,
            canvasWidth: $width,
            canvasHeight: $height,
        );
    }

    /**
     * Hoeveel van de canvasbreedte het product beslaat, als percentage.
     *
     * Hiermee krijgt een bijgesneden beeld exact de schaal terug die het op het
     * hele canvas had: de header rekent met een vierkante referentiebox ter
     * grootte van het canvas en geeft het beeld daarbinnen deze breedte. De
     * onderlinge verhouding tussen de ranges blijft daardoor ongemoeid — alleen
     * de lucht eromheen verdwijnt.
     */
    public function widthRatio(): float
    {
        return $this->width / $this->canvasWidth;
    }

    public function coversWholeCanvas(): bool
    {
        return $this->width === $this->canvasWidth && $this->height === $this->canvasHeight;
    }
}
