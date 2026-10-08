<?php

namespace App\Console\Commands;

use App\Actions\Auth\AuthenticateUser;
use Illuminate\Console\Command;

/**
 * Unlocks an account from the server, for the case where nobody can do it
 * from the back office (the only super-admin is locked out). Clears the
 * failed attempts counter and the lock; the password is not changed.
 */
class UnlockUser extends Command
{
    protected $signature = 'users:unlock {login : phone number or e-mail of the account}';

    protected $description = 'Débloque un compte verrouillé après trop d’essais (numéro de téléphone ou e-mail)';

    public function handle(AuthenticateUser $auth): int
    {
        $user = $auth->findUser((string) $this->argument('login'));

        if ($user === null) {
            $this->error('Aucun compte ne correspond à ce numéro ou à cette adresse e-mail.');

            return self::FAILURE;
        }

        $wasLocked = $user->isLocked();

        $user->forceFill([
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ])->save();

        activity('auth')->performedOn($user)
            ->event('unlock')
            ->withProperties(['via' => 'console', 'was_locked' => $wasLocked])
            ->log('Compte débloqué depuis le serveur');

        $this->info($wasLocked
            ? "Compte de {$user->name} débloqué."
            : "Le compte de {$user->name} n’était pas bloqué : compteur d’essais remis à zéro.");

        return self::SUCCESS;
    }
}
