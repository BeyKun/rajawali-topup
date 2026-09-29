<?php

namespace App\Rules;

use App\Support\Phone;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Validates that a value is a well-formed Telkomsel mobile number.
 *
 * The rule accepts local (`08…`) and international (`628…` / `8…`) formats,
 * normalizes the value and then checks the operator prefix against the
 * official Telkomsel range. Rejection messages are in Bahasa Indonesia.
 */
class TelkomselMsisdn implements ValidationRule
{
    /**
     * Prefixes (in canonical `62…` form) belonging to the Telkomsel network.
     *
     * @var list<string>
     */
    private const TELKOMSEL_PREFIXES = [
        '62811',
        '62812',
        '62813',
        '62821',
        '62822',
        '62823',
        '62851',
        '62852',
        '62853',
    ];

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

            return;
        }

        foreach (self::TELKOMSEL_PREFIXES as $prefix) {
            if (str_starts_with($normalized, $prefix)) {
                return;
            }
        }

        $fail('Nomor tujuan harus nomor Telkomsel (0811, 0812, 0813, 0821, 0822, 0823, 0851, 0852, 0853).');
    }
}
