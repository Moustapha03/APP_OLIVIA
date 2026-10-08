<?php

namespace App\Actions\Users;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\User;
use App\Support\TemporaryPassword;
use Illuminate\Support\Facades\DB;

/**
 * Account created by a manager. The person receives a temporary password
 * (shown once to the manager) and must choose their own at first login.
 */
class CreateUser
{
    /**
     * @param  array{first_name: string, last_name: string, phone: string, email?: string|null}  $data
     * @return array{0: User, 1: string} the user and the temporary password
     */
    public function handle(User $actor, array $data, RoleName $role): array
    {
        $temporaryPassword = TemporaryPassword::generate();

        $user = DB::transaction(function () use ($actor, $data, $role, $temporaryPassword) {
            $user = new User([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'phone' => $data['phone'],
                'email' => $data['email'] ?? null,
                'password' => $temporaryPassword,
            ]);

            $user->forceFill([
                'status' => UserStatus::Active,
                'must_change_password' => true,
            ])->save();

            $user->syncRoles([$role->value]);

            activity('users')->causedBy($actor)->performedOn($user)
                ->event('user.created')
                ->withProperties(['role' => $role->value])
                ->log('Compte créé ('.$role->label().')');

            return $user;
        });

        $user->sendEmailVerificationNotification();

        return [$user, $temporaryPassword];
    }
}
