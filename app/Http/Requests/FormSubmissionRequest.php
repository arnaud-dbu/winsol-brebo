<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\UploadedFile;
use Statamic\Http\Requests\FrontendFormRequest;

/**
 * Statamic's formulierverzoek, met twee correcties voor bijlagen.
 *
 * Statamic toetst elk bestand apart op `<handle>.*`, maar het sjabloon toont
 * alleen de fout op `<handle>`. Een geweigerd bestand liet het formulier dus
 * terugkomen zonder zichtbare reden. Daarom gaan de fouten per bestand hier
 * mee naar het veld zelf, achter die van het veld: AllowedFile meldt een
 * verkeerde extensie als "Het uploaden is mislukt.", en `mimes` op het veld
 * zegt dan al welk type wel mag.
 */
class FormSubmissionRequest extends FrontendFormRequest
{
    /**
     * Een bestand dat PHP al weigerde (te groot voor php.ini, half
     * aangekomen) heeft geen pad. Statamic's MimesRule en MaxRule kijken in
     * het bestand en gooien dan een exception. Die vallen weg; Laravels eigen
     * regels, zoals het aantal bestanden, blijven staan, en `<handle>.*`
     * meldt `uploaded`.
     */
    public function validator(): Validator
    {
        $validator = parent::validator();

        $geweigerd = collect($this->allFiles())
            ->filter(fn (mixed $bestanden): bool => collect($bestanden)->contains(
                fn (mixed $bestand): bool => $bestand instanceof UploadedFile && ! $bestand->isValid(),
            ))
            ->keys();

        if ($geweigerd->isNotEmpty()) {
            $regels = $validator->getRules();

            foreach ($geweigerd as $veld) {
                $regels[$veld] = array_values(array_filter($regels[$veld] ?? [], 'is_string'));
            }

            $validator->setRules($regels);
        }

        return $validator;
    }

    protected function failedValidation(Validator $validator): never
    {
        $fouten = $validator->errors();
        /** @var array<string, list<string>> $perBestand */
        $perBestand = [];
        /** @var array<string, list<string>> $perVeld */
        $perVeld = [];

        foreach ($fouten->messages() as $sleutel => $meldingen) {
            $veld = preg_replace('/\.\d+$/', '', $sleutel);
            $perVeld[$veld] ??= [];
            $perBestand[$veld] ??= [];

            if ($veld === $sleutel) {
                array_push($perVeld[$veld], ...$meldingen);
            } else {
                array_push($perBestand[$veld], ...$meldingen);
            }

            $fouten->forget($sleutel);
        }

        foreach ($perVeld as $veld => $meldingen) {
            foreach (array_unique([...$meldingen, ...$perBestand[$veld]]) as $melding) {
                $fouten->add($veld, $melding);
            }
        }

        parent::failedValidation($validator);
    }
}
