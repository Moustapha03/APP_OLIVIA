<?php

namespace App\Policies;

use App\Models\Line;
use App\Models\User;

/**
 * Read: lines.view. Write and delete: lines.manage. A record used by sales is
 * archived instead of deleted (RemoveFromNetwork action).
 */
class LinePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('lines.view');
    }

    public function view(User $actor, Line $model): bool
    {
        return $actor->can('lines.view');
    }

    public function create(User $actor): bool
    {
        return $actor->can('lines.manage');
    }

    public function update(User $actor, Line $model): bool
    {
        return $actor->can('lines.manage');
    }

    public function delete(User $actor, Line $model): bool
    {
        return $actor->can('lines.manage');
    }
}
