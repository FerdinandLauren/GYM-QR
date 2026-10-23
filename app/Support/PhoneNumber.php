<?php

namespace App\Support;

/**
 * Nomor HP disimpan dalam format E.164 tanpa tanda plus (AGENTS §4).
 */
final class PhoneNumber
{
    public static function normalize(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if (str_starts_with($digits, '0')) {
            return '62'.substr($digits, 1);
        }

        if (str_starts_with($digits, '8')) {
            return '62'.$digits;
        }

        return $digits;
    }

    public static function mask(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if (strlen($digits) <= 8) {
            return $digits;
        }

        return substr($digits, 0, 4).'****'.substr($digits, -4);
    }
}
