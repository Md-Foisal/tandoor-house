<?php

namespace App\Actions;

/**
 * Normalises and checks the UK formats checkout cares about.
 */
class UkFormats
{
    private const POSTCODE = '/^(GIR 0AA|[A-Z]{1,2}[0-9][A-Z0-9]? [0-9][A-Z]{2})$/';

    /**
     * "e1  6qr" becomes "E1 6QR"; null when it is not a UK postcode.
     */
    public static function postcode(?string $value): ?string
    {
        $compact = strtoupper((string) preg_replace('/\s+/', '', (string) $value));

        if (strlen($compact) < 5 || strlen($compact) > 7) {
            return null;
        }

        $normalised = substr($compact, 0, -3).' '.substr($compact, -3);

        return preg_match(self::POSTCODE, $normalised) === 1 ? $normalised : null;
    }

    /**
     * The first half of a postcode: "E14 9XY" gives "E14".
     */
    public static function outward(string $postcode): string
    {
        return explode(' ', $postcode)[0];
    }

    /**
     * "07700 900123" or "+44 7700 900123" becomes "+447700900123"; null when
     * it is not a UK mobile number.
     */
    public static function mobile(?string $value): ?string
    {
        $digits = (string) preg_replace('/[\s\-().]/', '', (string) $value);
        $digits = (string) preg_replace('/^(\+44|0044|44)(?=7)/', '0', $digits);

        if (preg_match('/^07[1-57-9]\d{8}$/', $digits) !== 1) {
            return null;
        }

        return '+44'.substr($digits, 1);
    }
}
