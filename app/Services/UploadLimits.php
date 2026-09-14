<?php

namespace App\Services;

/**
 * De werkelijke uploadgrenzen van deze server, in bytes.
 *
 * Bewust afgeleid en niet hardgecodeerd: de blueprint mag 10 MB per bestand
 * toestaan, maar als php.ini op `upload_max_filesize = 2M` staat, weigert PHP
 * het bestand voordat Laravel er iets van ziet. Erger nog bij `post_max_size`:
 * gaat de hele POST daaroverheen, dan gooit PHP body én $_POST weg, en krijgt
 * de bezoeker een leeg formulier terug zonder één foutmelding. De waarschuwing
 * onder het veld en de controle in de browser moeten dus dezelfde getallen
 * gebruiken als de server, op elke omgeving.
 */
class UploadLimits
{
    /**
     * Ruimte die van `post_max_size` afgaat voor de gewone velden, de
     * CSRF-token en de multipart-omhulsels rond elk bestand. Ruim genomen:
     * één megabyte minder mogen uploaden is onmerkbaar, één megabyte te veel
     * kost de hele inzending.
     */
    private const OVERHEAD = 1048576;

    public function __construct(
        /** Grens per bestand uit het blueprint, in kilobytes. */
        private readonly int $veldMaxKilobytes = 0,
    ) {}

    public static function voorVeld(int $maxKilobytes): self
    {
        return new self($maxKilobytes);
    }

    /**
     * Het kleinste van wat PHP en het blueprint per bestand toestaan.
     */
    public function perBestand(): int
    {
        $grenzen = array_filter([
            self::ini('upload_max_filesize'),
            $this->veldMaxKilobytes > 0 ? $this->veldMaxKilobytes * 1024 : null,
            $this->totaal(),
        ]);

        return $grenzen === [] ? 0 : (int) min($grenzen);
    }

    /**
     * Wat alle bestanden samen mogen wegen.
     */
    public function totaal(): int
    {
        $post = self::ini('post_max_size');

        if ($post === null) {
            return 0;
        }

        return (int) max(0, $post - self::OVERHEAD);
    }

    /**
     * Hoeveel bestanden PHP in één POST aanneemt. Nul betekent: geen grens
     * die wij hoeven te noemen.
     */
    public function maxBestanden(): int
    {
        $waarde = (int) ini_get('max_file_uploads');

        return $waarde > 0 ? $waarde : 0;
    }

    /**
     * @return array{per_bestand: int, totaal: int, max_bestanden: int, per_bestand_label: string, totaal_label: string}
     */
    public function toArray(): array
    {
        return [
            'per_bestand' => $this->perBestand(),
            'totaal' => $this->totaal(),
            'max_bestanden' => $this->maxBestanden(),
            'per_bestand_label' => self::label($this->perBestand()),
            'totaal_label' => self::label($this->totaal()),
        ];
    }

    /**
     * Naar beneden afgerond: een label dat meer belooft dan de server aanneemt
     * is erger dan geen label.
     */
    public static function label(int $bytes): string
    {
        if ($bytes <= 0) {
            return '';
        }

        // Nederlands en Frans schrijven een komma, Engels een punt. Hetzelfde
        // onderscheid als `toLocaleString` in form-feedback.js maakt.
        $komma = ! in_array(app()->getLocale(), ['en'], true);

        if ($bytes >= 1048576) {
            $mb = $bytes / 1048576;

            if ($mb >= 10) {
                return ((int) floor($mb)).' MB';
            }

            $afgerond = floor($mb * 10) / 10;

            // Een ronde waarde zonder decimaal: "2 MB" leest beter dan "2,0 MB".
            $getal = $afgerond == (int) $afgerond
                ? (string) (int) $afgerond
                : number_format($afgerond, 1, $komma ? ',' : '.', '');

            return $getal.' MB';
        }

        return ((int) floor($bytes / 1024)).' kB';
    }

    /**
     * php.ini schrijft groottes als `2M`, `512K` of `1G`; `-1` en `0` staan
     * voor onbeperkt.
     */
    private static function ini(string $sleutel): ?int
    {
        $waarde = trim((string) ini_get($sleutel));

        if ($waarde === '' || $waarde === '-1' || $waarde === '0') {
            return null;
        }

        $eenheid = strtolower(substr($waarde, -1));
        $getal = (int) $waarde;

        return match ($eenheid) {
            'g' => $getal * 1024 * 1024 * 1024,
            'm' => $getal * 1024 * 1024,
            'k' => $getal * 1024,
            default => $getal,
        };
    }
}
