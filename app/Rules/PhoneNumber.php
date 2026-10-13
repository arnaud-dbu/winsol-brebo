<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Een nummer dat te bellen is: nationaal met een 0 vooraan, of internationaal
 * met + of 00. Spaties, punten, schuine strepen, koppeltekens en een (0)
 * mogen; die schrijven klanten er zelf tussen.
 */
class PhoneNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $digits = preg_replace('~[\s./()-]~u', '', (string) $value);

        if (! preg_match('/^(?:(?:\+|00)[1-9]\d{7,13}|0[1-9]\d{7,9})$/', $digits)) {
            $fail('validation.phone')->translate();
        }
    }
}
