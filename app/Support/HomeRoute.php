<?php

namespace App\Support;

use App\Models\User;

/**
 * Where a user lands after login, decided by permissions (never by role).
 */
final class HomeRoute
{
    /** Space permission => home route, in priority order. */
    private const SPACES = [
        'space.admin' => 'admin.dashboard',
        'space.guichet' => 'guichet.home',
        'space.controle' => 'controle.home',
        'space.espace' => 'espace.home',
    ];

    /**
     * Route name of the user's home, or null when no space is open to them.
     */
    public static function name(User $user): ?string
    {
        foreach (self::SPACES as $permission => $route) {
            if ($user->can($permission)) {
                return $route;
            }
        }

        return null;
    }

    public static function url(User $user): string
    {
        $name = self::name($user);

        return $name !== null ? route($name) : route('home');
    }
}
