<?php

namespace App\Policies;

use App\Models\CashSession;
use App\Models\User;

/**
 * An agent only sees and closes their own cash register; accountants and
 * managers with "cash.view-all" see every register.
 */
class CashSessionPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('cash.view-all');
    }

    public function view(User $actor, CashSession $cashSession): bool
    {
        return $actor->can('cash.view-all')
            || ($actor->can('cash.open') && $cashSession->user_id === $actor->id);
    }

    public function open(User $actor): bool
    {
        return $actor->can('cash.open');
    }

    public function close(User $actor, CashSession $cashSession): bool
    {
        return $actor->can('cash.close')
            && $cashSession->user_id === $actor->id
            && $cashSession->isOpen();
    }

    public function reconcile(User $actor, CashSession $cashSession): bool
    {
        return $actor->can('cash.reconcile') && ! $cashSession->isOpen();
    }
}
