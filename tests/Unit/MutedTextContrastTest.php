<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Hulptekst in de formulieren (optioneel, uitleg, de dropzone) stond in
 * zwart met 40 à 55% dekking. Op het lichte vlak van de offertekaart gaf dat
 * 2,5:1 en 3,8:1 (Lighthouse, #10). Nu is het één token, `muted`, en die
 * moet op beide achtergronden van de formulieren 4,5:1 halen.
 */
class MutedTextContrastTest extends TestCase
{
    #[DataProvider('achtergronden')]
    public function test_muted_text_reads_on_every_form_background(string $achtergrond): void
    {
        $this->assertGreaterThanOrEqual(4.5, $this->contrast($this->token('muted'), $this->token($achtergrond)));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function achtergronden(): array
    {
        return [
            'wit' => ['white'],
            'light' => ['light'],
        ];
    }

    #[DataProvider('hulpteksten')]
    public function test_the_form_help_text_uses_the_token(string $bestand, string $regel): void
    {
        $css = file_get_contents(dirname(__DIR__, 2).'/resources/css/'.$bestand);

        $this->assertMatchesRegularExpression(
            '/'.preg_quote($regel, '/').'\s*\{[^}]*\btext-muted\b/',
            $css,
            "{$regel} in {$bestand} gebruikt de token niet.",
        );
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function hulpteksten(): array
    {
        return [
            'verplicht-notitie' => ['base/typography.css', '@utility form-required-note'],
            'optioneel' => ['base/typography.css', '@utility form-optional'],
            'uitleg' => ['components/form.css', '.form-instructions'],
            'dropzone' => ['components/form.css', '.form-dropzone'],
        ];
    }

    private function token(string $naam): string
    {
        $css = file_get_contents(dirname(__DIR__, 2).'/resources/css/site.css');

        preg_match('/--color-'.$naam.':\s*(#[0-9a-f]{3,6});/i', $css, $match);

        $this->assertNotEmpty($match, "Token --color-{$naam} ontbreekt in site.css.");

        return $match[1];
    }

    private function contrast(string $voor, string $achter): float
    {
        $licht = [$this->luminantie($voor), $this->luminantie($achter)];

        return (max($licht) + 0.05) / (min($licht) + 0.05);
    }

    private function luminantie(string $hex): float
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        $kanalen = array_map(function (string $paar): float {
            $waarde = hexdec($paar) / 255;

            return $waarde <= 0.03928 ? $waarde / 12.92 : (($waarde + 0.055) / 1.055) ** 2.4;
        }, str_split($hex, 2));

        return 0.2126 * $kanalen[0] + 0.7152 * $kanalen[1] + 0.0722 * $kanalen[2];
    }
}
