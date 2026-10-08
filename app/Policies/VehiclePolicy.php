<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vehicle;

/**
 * Read: vehicles.view. Write and delete: vehicles.manage (no sale refers to a
 * vehicle: always deleted).
 */
class VehiclePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('vehicles.view');
    }

    public function view(User $actor, Vehicle $model): bool
    {
        return $actor->can('vehicles.view');
    }

    public function create(User $actor): bool
    {
        return $actor->can('vehicles.manage');
    }

    public function update(User $actor, Vehicle $model): bool
    {
        return $actor->can('vehicles.manage');
    }

    public function delete(User $actor, Vehicle $model): bool
    {
        return $actor->can('vehicles.manage');
    }
}
