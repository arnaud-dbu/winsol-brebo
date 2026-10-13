<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Het moment waarop een formulier geladen werd, als verborgen veld in het
 * formulier zelf. Versleuteld met de app-sleutel, zodat een bot het niet kan
 * terugzetten, en zonder sessie of cookie, zodat het ook werkt voor wie
 * cookies weigert.
 */
class FormTimer
{
    public const FIELD = '_loaded';

    public static function stamp(?CarbonInterface $at = null): string
    {
        return Crypt::encryptString((string) ($at ?? now())->getTimestamp());
    }

    /**
     * Null wanneer het veld ontbreekt of niet van deze site komt.
     */
    public static function secondsSinceLoad(mixed $token): ?int
    {
        if (! is_string($token) || $token === '') {
            return null;
        }

        try {
            $loaded = Crypt::decryptString($token);
        } catch (DecryptException) {
            return null;
        }

        return now()->getTimestamp() - (int) $loaded;
    }
}
