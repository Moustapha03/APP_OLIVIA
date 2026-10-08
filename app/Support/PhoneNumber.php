<?php

namespace App\Support;

/**
 * Ivorian phone numbers (10 digits since 2021).
 *
 * Stored in E.164 form: +2250700000000. Accepts what people actually type:
 * "07 00 00 00 00", "07.00.00.00.00", "+225 0700000000", "002250700000000".
 */
final class PhoneNumber
{
    public const COUNTRY_CODE = '+225';

    /** Mobile (01 Moov, 05 MTN, 07 Orange) and fixed (21, 25, 27) prefixes. */
    public const PATTERN = '/^\+225(01|05|07|21|25|27)\d{8}$/';

    /**
     * Normalized E.164 number, or null when the input is not a valid number.
     */
    public static function normalize(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $input) ?? '';

        if (str_starts_with($digits, '00225')) {
            $digits = substr($digits, 5);
        } elseif (str_starts_with($digits, '225') && strlen($digits) === 13) {
            $digits = substr($digits, 3);
        }

        $e164 = self::COUNTRY_CODE.$digits;

        return preg_match(self::PATTERN, $e164) === 1 ? $e164 : null;
    }

    /**
     * Human display: "07 00 00 00 00".
     */
    public static function format(?string $e164): string
    {
        if ($e164 === null || ! str_starts_with($e164, self::COUNTRY_CODE)) {
            return (string) $e164;
        }

        return trim(chunk_split(substr($e164, strlen(self::COUNTRY_CODE)), 2, ' '));
    }
}
