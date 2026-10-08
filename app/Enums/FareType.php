<?php

namespace App\Enums;

/**
 * What can be bought, priced per zone (fares.type, payments.purpose).
 * The three subscriptions are calendar months ("octobre 2026").
 */
enum FareType: string
{
    case Daily = 'daily';
    case Monthly = 'monthly';                // Matin & soir
    case MonthlyMorning = 'monthly_morning'; // Matin simple
    case MonthlyEvening = 'monthly_evening'; // Soir simple

    public function label(): string
    {
        return match ($this) {
            self::Daily => 'Ticket du jour',
            self::Monthly => 'Abonnement matin & soir',
            self::MonthlyMorning => 'Abonnement matin',
            self::MonthlyEvening => 'Abonnement soir',
        };
    }

    /**
     * Name used on the client's receipts and exports.
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Daily => 'Ticket du jour',
            self::Monthly => 'Matin & soir',
            self::MonthlyMorning => 'Matin simple',
            self::MonthlyEvening => 'Soir simple',
        };
    }

    public function isSubscription(): bool
    {
        return $this !== self::Daily;
    }

    /**
     * Periods of the day this formula lets the holder ride.
     *
     * @return list<Period>
     */
    public function periods(): array
    {
        return match ($this) {
            self::Daily, self::Monthly => [Period::Morning, Period::Evening],
            self::MonthlyMorning => [Period::Morning],
            self::MonthlyEvening => [Period::Evening],
        };
    }

    /**
     * @return list<self>
     */
    public static function subscriptions(): array
    {
        return [self::Monthly, self::MonthlyMorning, self::MonthlyEvening];
    }
}
