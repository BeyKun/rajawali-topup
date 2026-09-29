<?php

namespace App\Support;

/**
 * Helpers for normalizing Indonesian mobile numbers into the canonical
 * E.164-style `628xxx` representation used across the platform.
 */
class Phone
{
    /**
     * Normalize an Indonesian phone number into `628xxx` format.
     *
     * Strips every non-digit character and converts local prefixes (`08…`) or
     * bare prefixes (`8…`) into the international `62…` form. Numbers already
     * in the `62…` form are returned unchanged.
     */
    public static function normalizeTo62(string $phone): string
    {
        $digits = (string) preg_replace('/[^0-9]/', '', $phone);

        if (str_starts_with($digits, '08')) {
            return '62'.substr($digits, 1);
        }

        if (str_starts_with($digits, '8')) {
            return '62'.$digits;
        }

        return $digits;
    }
}
