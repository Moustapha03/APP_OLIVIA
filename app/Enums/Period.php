<?php

namespace App\Enums;

/**
 * Trips of the day: morning (towards the destination) and evening (back).
 */
enum Period: string
{
    case Morning = 'morning';
    case Evening = 'evening';

    public function label(): string
    {
        return match ($this) {
            self::Morning => 'Matin',
            self::Evening => 'Soir',
        };
    }
}
