<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;

/**
 * Read: expenses.view (direction, comptable, gestionnaire).
 * Write and delete (archive): expenses.manage (comptable, gestionnaire).
 */
class ExpensePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('expenses.view');
    }

    public function view(User $actor, Expense $expense): bool
    {
        return $actor->can('expenses.view');
    }

    public function create(User $actor): bool
    {
        return $actor->can('expenses.manage');
    }

    public function update(User $actor, Expense $expense): bool
    {
        return $actor->can('expenses.manage');
    }

    public function delete(User $actor, Expense $expense): bool
    {
        return $actor->can('expenses.manage');
    }
}
