<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Een klasse en geen `between` in het blueprint: de bovengrens is het
 * huidige jaar.
 */
class InstallationYear implements ValidationRule
{
    private const EARLIEST = 1950;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $value = trim((string) $value);

        if (! preg_match('/^\d{4}$/', $value) || (int) $value < self::EARLIEST || (int) $value > now()->year) {
            $fail('validation.installation_year')->translate(['first' => self::EARLIEST, 'year' => now()->year]);
        }
    }
}
