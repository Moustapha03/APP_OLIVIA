<?php

namespace App\Actions\Users;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Edit an account: identity, role (one per person) and status.
 *
 * Guards on top of UserPolicy (which the super-admin bypasses):
 * - nobody changes their own role or disables themselves;
 * - the last active super-admin can be neither demoted nor disabled.
 * Role, status, phone and e-mail changes are written to the audit log
 * (old and new values: staff data, nothing masked).
 */
class UpdateUser
{
    /**
     * @param  array{first_name: string, last_name: string, phone: string, email?: string|null}  $data
     *
     * @throws ValidationException
     */
    public function handle(User $actor, User $target, array $data, RoleName $role, UserStatus $status): User
    {
        return DB::transaction(function () use ($actor, $target, $data, $role, $status) {
            $oldRole = $target->getRoleNames()->first();
            $oldStatus = $target->status;
            $roleChanged = $oldRole !== $role->value;
            $statusChanged = $oldStatus !== $status;

            if ($actor->is($target) && ($roleChanged || $statusChanged)) {
                throw ValidationException::withMessages([
                    'role' => 'Vous ne pouvez pas modifier votre propre rôle ni désactiver votre propre compte.',
                ]);
            }

            if ($target->isSuperAdmin() && ($role !== RoleName::SuperAdmin || $status !== UserStatus::Active)) {
                $this->ensureAnotherActiveSuperAdmin($target);
            }

            $target->fill([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'phone' => $data['phone'],
                'email' => $data['email'] ?? null,
            ]);

            $emailChanged = $target->isDirty('email');
            if ($emailChanged) {
                $target->email_verified_at = null;
            }

            // Phone and e-mail are the login: every change is kept, old and new.
            $identityChanges = collect(['phone', 'email'])
                ->filter(fn (string $field) => $target->isDirty($field))
                ->mapWithKeys(fn (string $field) => [$field => ['old' => $target->getOriginal($field), 'new' => $target->getAttribute($field)]])
                ->all();

            $target->status = $status;
            $target->save();

            if ($identityChanges !== []) {
                activity('users')->causedBy($actor)->performedOn($target)
                    ->event('user.identity-changed')
                    ->withProperties($identityChanges)
                    ->log('Identifiant de connexion modifié : '.implode(', ', array_map(
                        fn (string $field) => $field === 'phone' ? 'téléphone' : 'e-mail',
                        array_keys($identityChanges),
                    )));
            }

            if ($roleChanged) {
                $target->syncRoles([$role->value]);

                activity('users')->causedBy($actor)->performedOn($target)
                    ->event('user.role-changed')
                    ->withProperties(['old' => $oldRole, 'new' => $role->value])
                    ->log('Rôle modifié : '.($oldRole ?? 'aucun').' → '.$role->value);
            }

            if ($statusChanged) {
                activity('users')->causedBy($actor)->performedOn($target)
                    ->event('user.status-changed')
                    ->withProperties(['old' => $oldStatus->value, 'new' => $status->value])
                    ->log($status === UserStatus::Disabled ? 'Compte désactivé' : 'Compte réactivé');
            }

            if ($emailChanged && $target->email !== null) {
                $target->sendEmailVerificationNotification();
            }

            return $target;
        });
    }

    /**
     * @throws ValidationException
     */
    private function ensureAnotherActiveSuperAdmin(User $target): void
    {
        $others = User::role(RoleName::SuperAdmin->value)
            ->whereKeyNot($target->getKey())
            ->where('status', UserStatus::Active)
            ->lockForUpdate()
            ->count();

        if ($others === 0) {
            throw ValidationException::withMessages([
                'role' => 'C’est le dernier super-administrateur actif : nommez-en un autre avant de le modifier.',
            ]);
        }
    }
}
