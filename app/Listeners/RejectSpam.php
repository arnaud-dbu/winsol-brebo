<?php

namespace App\Listeners;

use App\Services\FormTimer;
use Illuminate\Support\Facades\Log;
use Statamic\Events\FormSubmitted;

/**
 * Spamfilter op de formulieren, zonder captcha en zonder diensten van derden.
 *
 * `false` laat Statamic de inzending stil weggooien, net als bij
 * VerifyRecaptcha: de bot krijgt het bedankscherm en dus geen foutmelding om
 * op bij te sturen. Een onterecht geweigerde klant krijgt datzelfde scherm,
 * dus elke weigering komt in het log met formulier en reden. Geen waarden,
 * zoals bij LogRejectedFormSubmissions.
 */
class RejectSpam
{
    private const MINIMUM_SECONDS = 3;

    /**
     * Ruim genoeg voor wie een tabblad een nacht laat openstaan, kort genoeg
     * dat een bot niet één laadtijd blijft hergebruiken.
     */
    private const MAXIMUM_HOURS = 24;

    /**
     * Velden waarin een link nooit thuishoort. In een beschrijving mag hij
     * wel: een klant verwijst soms naar een voorbeeld. Het telefoonveld staat
     * er niet bij, want App\Rules\PhoneNumber weigert een link daar al bij de
     * validatie.
     */
    private const LINK_FREE_FIELDS = ['name', 'address'];

    private const LINK_PATTERN = '~https?://|www\.|[\p{L}\d-]+\.(?:com|net|org|info|biz|ru|xyz|top|online|site|club|bet|casino|io)\b|[\p{L}\d-]+\.[a-z]{2,}/~iu';

    public function handle(FormSubmitted $event): ?false
    {
        $reason = $this->reason($event->submission->data()->all());

        if ($reason === null) {
            return null;
        }

        Log::warning('Inzending geweigerd als spam.', [
            'formulier' => $event->submission->form()->handle(),
            'reden' => $reason,
        ]);

        return false;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function reason(array $data): ?string
    {
        $seconds = FormTimer::secondsSinceLoad(request()->input(FormTimer::FIELD));

        if ($seconds === null) {
            return 'geen laadtijd';
        }

        if ($seconds < self::MINIMUM_SECONDS) {
            return 'te snel verstuurd';
        }

        if ($seconds > self::MAXIMUM_HOURS * 3600) {
            return 'laadtijd verlopen';
        }

        foreach (self::LINK_FREE_FIELDS as $field) {
            if (is_string($data[$field] ?? null) && preg_match(self::LINK_PATTERN, $data[$field])) {
                return "link in {$field}";
            }
        }

        foreach ($data as $field => $value) {
            if (is_string($value) && preg_match('/\p{Cyrillic}/u', $value)) {
                return "Cyrillisch schrift in {$field}";
            }
        }

        return null;
    }
}
