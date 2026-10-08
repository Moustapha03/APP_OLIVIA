<?php

namespace App\Enums;

/**
 * Administrative status of a pass. Whether it can still be used today
 * (expired, rides used up…) is computed from its dates and counters.
 */
enum PassStatus: string
{
    case Active = 'active';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Actif',
            self::Cancelled => 'Annulé',
            self::Refunded => 'Remboursé',
        };
    }
}
