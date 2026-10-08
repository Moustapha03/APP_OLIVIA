<?php

namespace App\Policies;

use App\Models\Fare;
use App\Models\User;

/**
 * "manage" = set a price from a date, correct the price in force or a
 * scheduled one, remove a price (see App\Actions\Fares\RemoveFare).
 * Old prices are history: read only.
 */
class FarePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('fares.view');
    }

    public function view(User $actor, Fare $fare): bool
    {
        return $actor->can('fares.view');
    }

    public function create(User $actor): bool
    {
        return $actor->can('fares.manage');
    }

    public function update(User $actor, Fare $fare): bool
    {
        return $actor->can('fares.manage') && ! $this->isPast($fare);
    }

    public function delete(User $actor, Fare $fare): bool
    {
        return $actor->can('fares.manage') && ! $this->isPast($fare);
    }

    private function isPast(Fare $fare): bool
    {
        return $fare->effective_to !== null && $fare->effective_to->lt(today());
    }
}
