<?php

namespace App\Tags;

use App\Services\UploadLimits as Limieten;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Statamic\Tags\Tags;

/**
 * De uploadgrenzen van een bestandsveld, als tagpaar.
 *
 *     {{ upload_limits :validate="validate" :max_files="max_files" }}
 *         {{ per_bestand_label }} per bestand, {{ totaal_label }} in totaal
 *     {{ /upload_limits }}
 *
 * De grens per bestand komt uit `max_filesize` in het blueprint, maar wordt
 * afgetopt op wat php.ini toestaat — zie App\Services\UploadLimits voor
 * waarom dat verschil ertoe doet.
 */
class UploadLimits extends Tags
{
    protected static $handle = 'upload_limits';

    /**
     * @return array<string, mixed>
     */
    public function index(): array
    {
        $limieten = Limieten::voorVeld($this->maxKilobytes());

        $waarden = $limieten->toArray();

        // Het blueprint bepaalt hoeveel bestanden de bezoeker mag kiezen, PHP
        // hoeveel er in één POST passen. De strengste van de twee telt.
        $veldMax = (int) $this->params->get('max_files');
        $phpMax = $limieten->maxBestanden();

        $waarden['max_bestanden'] = match (true) {
            $veldMax > 0 && $phpMax > 0 => min($veldMax, $phpMax),
            $veldMax > 0 => $veldMax,
            default => $phpMax,
        };

        $waarden['meerdere'] = $waarden['max_bestanden'] !== 1;

        return $waarden;
    }

    /**
     * `max_filesize:10240` uit de validatieregels van het veld.
     */
    private function maxKilobytes(): int
    {
        foreach (Arr::wrap($this->params->get('validate')) as $regel) {
            if (is_string($regel) && Str::startsWith($regel, 'max_filesize:')) {
                return (int) Str::after($regel, 'max_filesize:');
            }
        }

        return 0;
    }
}
