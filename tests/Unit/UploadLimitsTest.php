<?php

namespace Tests\Unit;

use App\Services\UploadLimits;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UploadLimitsTest extends TestCase
{
    public function test_de_grens_per_bestand_is_de_strengste_van_php_en_het_blueprint(): void
    {
        $limieten = UploadLimits::voorVeld(10240);

        $phpGrens = $this->naarBytes(ini_get('upload_max_filesize'));

        // Het blueprint staat 10 MB toe; zodra php.ini strenger is, telt die.
        $this->assertSame(
            min($phpGrens, 10240 * 1024, $limieten->totaal()),
            $limieten->perBestand(),
        );
    }

    public function test_geen_enkel_bestand_mag_groter_zijn_dan_het_totaal(): void
    {
        $limieten = UploadLimits::voorVeld(10240);

        $this->assertLessThanOrEqual($limieten->totaal(), $limieten->perBestand());
    }

    public function test_het_totaal_laat_ruimte_voor_de_rest_van_het_formulier(): void
    {
        $limieten = UploadLimits::voorVeld(10240);

        // Anders sneuvelt een inzending die precies op post_max_size zit alsnog
        // op de gewone velden en de csrf-token.
        $this->assertLessThan($this->naarBytes(ini_get('post_max_size')), $limieten->totaal());
    }

    public function test_een_veld_zonder_eigen_grens_valt_terug_op_php(): void
    {
        $limieten = UploadLimits::voorVeld(0);

        $this->assertGreaterThan(0, $limieten->perBestand());
    }

    #[DataProvider('labels')]
    public function test_het_label_rondt_naar_beneden_af(int $bytes, string $verwacht): void
    {
        $this->app->setLocale('nl');

        $this->assertSame($verwacht, UploadLimits::label($bytes));
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function labels(): array
    {
        return [
            'niets' => [0, ''],
            'kilobytes' => [512 * 1024, '512 kB'],
            'ronde megabyte' => [2 * 1048576, '2 MB'],
            'halve megabyte' => [(int) (2.5 * 1048576), '2,5 MB'],
            // Naar beneden: een label dat meer belooft dan de server aanneemt
            // stuurt de bezoeker het mes in.
            'rondt niet omhoog' => [(int) (7.99 * 1048576), '7,9 MB'],
            'groot getal zonder decimaal' => [25 * 1048576, '25 MB'],
        ];
    }

    public function test_het_label_volgt_de_taal_van_de_bezoeker(): void
    {
        $bytes = (int) (2.5 * 1048576);

        $this->app->setLocale('fr');
        $this->assertSame('2,5 MB', UploadLimits::label($bytes));

        $this->app->setLocale('en');
        $this->assertSame('2.5 MB', UploadLimits::label($bytes));
    }

    private function naarBytes(string $waarde): int
    {
        $eenheid = strtolower(substr(trim($waarde), -1));
        $getal = (int) $waarde;

        return match ($eenheid) {
            'g' => $getal * 1024 * 1024 * 1024,
            'm' => $getal * 1024 * 1024,
            'k' => $getal * 1024,
            default => $getal,
        };
    }
}
