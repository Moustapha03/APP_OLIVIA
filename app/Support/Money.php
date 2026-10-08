<?php

namespace App\Support;

/**
 * FCFA amounts are stored as integers (no decimals).
 */
final class Money
{
    /** Non-breaking space: "1 500 FCFA" never wraps across lines. */
    private const NBSP = "\u{00A0}";

    public static function format(int $amount, bool $withCurrency = true): string
    {
        $formatted = number_format($amount, 0, ',', self::NBSP);

        return $withCurrency ? $formatted.self::NBSP.'FCFA' : $formatted;
    }
}
