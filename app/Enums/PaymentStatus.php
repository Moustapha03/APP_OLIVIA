<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    // Cash sale cancelled at the counter with a supervisor (rule 9): money given back.
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Succeeded => 'Payé',
            self::Failed => 'Échoué',
            self::Cancelled => 'Annulé',
        };
    }

    public function isFinal(): bool
    {
        return $this !== self::Pending;
    }
}
