<?php

namespace App\Support;

/**
 * Temporary password read aloud or copied from a screen at the ticket
 * office: 3 groups of 4 characters, without look-alikes (0/O, 1/l/I).
 * Example: "k7mp-9wrt-x3hd". The user must change it at first login.
 */
final class TemporaryPassword
{
    private const ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';

    public static function generate(): string
    {
        $groups = [];

        for ($group = 0; $group < 3; $group++) {
            $chars = '';
            for ($i = 0; $i < 4; $i++) {
                $chars .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
            $groups[] = $chars;
        }

        return implode('-', $groups);
    }
}
