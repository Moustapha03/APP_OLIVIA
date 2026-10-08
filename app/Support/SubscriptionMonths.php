<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Subscriptions are sold for a CALENDAR month (the agency's "octobre 2026").
 * On sale: the current month and the next one (paid in advance at the end
 * of the month, as the agency does). Always the full month price.
 */
final class SubscriptionMonths
{
    /**
     * @return list<Carbon> first day of each month on sale
     */
    public static function onSale(?Carbon $today = null): array
    {
        $current = ($today ?? today())->copy()->startOfMonth();

        return [$current, $current->copy()->addMonthNoOverflow()];
    }

    public static function isOnSale(Carbon $month, ?Carbon $today = null): bool
    {
        $month = $month->copy()->startOfMonth();

        foreach (self::onSale($today) as $allowed) {
            if ($allowed->isSameDay($month)) {
                return true;
            }
        }

        return false;
    }

    /**
     * "2026-10" → first day of October 2026, or null when invalid.
     */
    public static function parse(?string $value): ?Carbon
    {
        if ($value === null || preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value) !== 1) {
            return null;
        }

        return Carbon::createFromFormat('Y-m-d', $value.'-01')?->startOfDay();
    }

    /**
     * "octobre 2026"
     */
    public static function label(Carbon $month): string
    {
        return $month->translatedFormat('F Y');
    }
}
