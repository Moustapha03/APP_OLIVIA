<?php

namespace App\Enums;

enum PassChannel: string
{
    case Online = 'online';
    case Counter = 'counter';

    public function label(): string
    {
        return match ($this) {
            self::Online => 'En ligne',
            self::Counter => 'Guichet',
        };
    }
}
