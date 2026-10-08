<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\User;
use App\Support\PermissionCatalog;
use Spatie\Permission\Exceptions\RoleDoesNotExist;
use Spatie\Permission\Models\Role;

/**
 * Rules for managing accounts (screens in step 4).
 * The super-admin passes everything through Gate::before.
 *
 * A manager (users.manage) can never touch a super-admin account, never
 * grant the super-admin role, and never change their own roles.
 *
 * No privilege escalation: a manager only gives a role whose permissions
 * they ALL hold themselves (the "client" role, without any staff right,
 * stays open). Resetting the password of a staff account and changing a
 * staff account's phone or e-mail (= its login) are for the super-admin
 * only: otherwise a manager could take over a more privileged account.
 */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('users.view');
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->is($target) || $actor->can('users.view');
    }

    public function create(User $actor): bool
    {
        return $actor->can('users.manage');
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->can('users.manage') && ! $target->isSuperAdmin();
    }

    public function delete(User $actor, User $target): bool
    {
        return $actor->can('users.manage')
            && ! $target->isSuperAdmin()
            && ! $actor->is($target);
    }

    public function assignRole(User $actor, User $target, RoleName $role): bool
    {
        return $actor->can('users.manage')
            && $role !== RoleName::SuperAdmin
            && ! $target->isSuperAdmin()
            && ! $actor->is($target)
            && ($role === RoleName::Client || $this->holdsEveryPermissionOf($actor, $role));
    }

    /**
     * Change the phone number or e-mail (the login) of an existing account.
     * For a staff account: super-admin only (Gate::before).
     */
    public function changeIdentity(User $actor, User $target): bool
    {
        return $this->update($actor, $target) && ! $target->isStaff();
    }

    /**
     * Temporary password given at the ticket office or in the back office
     * (customer without a verified e-mail). Staff accounts: super-admin only
     * (Gate::before); a locked super-admin uses `php artisan users:unlock`.
     */
    public function resetPassword(User $actor, User $target): bool
    {
        if ($actor->is($target) || $target->isSuperAdmin() || $target->isStaff()) {
            return false;
        }

        return $actor->can('customers.reset-password') || $actor->can('users.manage');
    }

    /**
     * The role's default permissions (PermissionCatalog) plus those given to
     * it on the roles screen: the actor must hold every one of them.
     */
    private function holdsEveryPermissionOf(User $actor, RoleName $role): bool
    {
        try {
            $current = Role::findByName($role->value, 'web')->permissions->pluck('name')->all();
        } catch (RoleDoesNotExist) {
            $current = [];
        }

        return collect([...PermissionCatalog::forRole($role), ...$current])
            ->unique()
            ->every(fn (string $permission): bool => $actor->can($permission));
    }
}
