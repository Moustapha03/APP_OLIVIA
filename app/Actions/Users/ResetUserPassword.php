<?php

namespace App\Actions\Users;

use App\Models\User;
use App\Support\TemporaryPassword;
use Illuminate\Support\Str;

/**
 * Gives a temporary password (person without a verified e-mail, or locked
 * out). The account is unlocked, other sessions stop being remembered,
 * and a new password must be chosen at next login.
 */
class ResetUserPassword
{
    public function handle(User $actor, User $target): string
    {
        $temporaryPassword = TemporaryPassword::generate();

        $target->forceFill([
            'password' => $temporaryPassword,
            'must_change_password' => true,
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'remember_token' => Str::random(60),
        ])->save();

        activity('users')->causedBy($actor)->performedOn($target)
            ->event('user.password-reset')
            ->log('Mot de passe provisoire attribué');

        return $temporaryPassword;
    }
}
