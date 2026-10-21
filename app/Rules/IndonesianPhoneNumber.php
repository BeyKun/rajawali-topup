<?php

namespace App\Rules;

use App\Support\Phone;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Validates a well-formed Indonesian mobile number on any operator.
 *
 * Unlike {@see TelkomselMsisdn}, which guards order destination numbers, this
 * rule is used for the outlet owner's own contact/login number, so any
 * Indonesian mobile prefix (`08…` / `628…`) is accepted.
 */
class IndonesianPhoneNumber implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) && ! is_numeric($value)) {
            $fail('Nomor HP tidak valid.');

            return;
        }

        $normalized = Phone::normalizeTo62((string) $value);

        if (preg_match('/^628\d{7,11}$/', $normalized) !== 1) {
            $fail('Nomor HP tidak valid. Gunakan format 08xxxxxxxxxx.');
        }
    }
}
