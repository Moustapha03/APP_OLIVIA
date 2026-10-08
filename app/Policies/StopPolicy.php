<?php

namespace App\Policies;

use App\Models\Stop;
use App\Models\User;

/**
 * Read: stops.view. Write and delete: stops.manage. A record used by sales is
 * archived instead of deleted (RemoveFromNetwork action).
 */
class StopPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('stops.view');
    }

    public function view(User $actor, Stop $model): bool
    {
        return $actor->can('stops.view');
    }

    public function create(User $actor): bool
    {
        return $actor->can('stops.manage');
    }

    public function update(User $actor, Stop $model): bool
    {
        return $actor->can('stops.manage');
    }

    public function delete(User $actor, Stop $model): bool
    {
        return $actor->can('stops.manage');
    }
}
