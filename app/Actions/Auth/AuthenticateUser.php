<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Phone number (or e-mail) + password login, with account lockout.
 *
 * - wrong password: counter +1; at MAX_LOGIN_ATTEMPTS the account is locked
 *   for LOCKOUT_MINUTES (audit logged). Once a lock has expired, the next
 *   failure starts a fresh counter (no permanent lockout).
 * - a disabled account is refused, but only once the password is right
 *   (no hint given to someone who does not know the password).
 * - "remember me" is honoured for customers only: staff sessions always
 *   expire after SESSION_LIFETIME minutes of inactivity.
 */
class AuthenticateUser
{
    private static ?string $dummyHash = null;

    /**
     * @throws ValidationException
     */
    public function handle(string $login, string $password, bool $remember, string $ip): User
    {
        $user = $this->findUser($login);

        if ($user === null) {
            // Same work as a real check, so response time does not reveal unknown numbers.
            self::checkAgainstDummy($password);

            throw $this->fail('Numéro ou mot de passe incorrect.');
        }

        if ($user->isLocked()) {
            throw $this->fail($this->lockedMessage($user));
        }

        if (! Hash::check($password, $user->password)) {
            $this->registerFailure($user, $ip);

            throw $this->fail($user->isLocked()
                ? $this->lockedMessage($user)
                : $this->remainingAttemptsMessage($user));
        }

        if (! $user->isActive()) {
            throw $this->fail('Votre compte a été désactivé. Contactez la compagnie de transport.');
        }

        Auth::login($user, $remember && ! $user->isStaff());

        $user->forceFill([
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'last_login_at' => now(),
            'last_login_ip' => $ip,
        ])->save();

        if ($user->isStaff()) {
            activity('auth')->causedBy($user)->performedOn($user)
                ->event('login')
                ->withProperties(['ip' => $ip])
                ->log('Connexion du personnel');
        }

        return $user;
    }

    /**
     * Burns the time of a real password check (unknown account), so the
     * response time reveals nothing. Always false.
     */
    public static function checkAgainstDummy(string $password): bool
    {
        Hash::check($password, self::$dummyHash ??= Hash::make(Str::random(32)));

        return false;
    }

    /**
     * Account matching a typed login: phone number (any usual format) or e-mail.
     */
    public function findUser(string $login): ?User
    {
        $phone = PhoneNumber::normalize($login);

        if ($phone !== null) {
            return User::query()->where('phone', $phone)->first();
        }

        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            return User::query()->where('email', mb_strtolower(trim($login)))->first();
        }

        return null;
    }

    /**
     * Wrong password typed for this account (login form, or supervisor
     * check at the ticket office). Locks the account at MAX_LOGIN_ATTEMPTS.
     */
    public function registerFailure(User $user, string $ip): void
    {
        $max = (int) config('transport.security.max_login_attempts');

        DB::transaction(function () use ($user, $ip, $max) {
            /** @var User $locked */
            $locked = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            // An expired lock starts a fresh counter: otherwise the counter
            // stays at the maximum and a single typo locks the account again.
            if ($locked->locked_until !== null && ! $locked->locked_until->isFuture()) {
                $locked->failed_login_attempts = 0;
                $locked->locked_until = null;
            }

            $locked->failed_login_attempts++;

            if ($locked->failed_login_attempts >= $max) {
                $locked->locked_until = now()->addMinutes((int) config('transport.security.lockout_minutes'));

                activity('auth')->performedOn($locked)
                    ->event('lockout')
                    ->withProperties(['ip' => $ip, 'attempts' => $locked->failed_login_attempts])
                    ->log('Compte verrouillé après trop d’essais');
            }

            $locked->save();
            $user->setRawAttributes($locked->getAttributes(), true);
        });
    }

    private function remainingAttemptsMessage(User $user): string
    {
        $left = (int) config('transport.security.max_login_attempts') - $user->failed_login_attempts;

        return $left === 1
            ? 'Mot de passe incorrect. Attention : il vous reste 1 essai.'
            : "Mot de passe incorrect. Il vous reste {$left} essais.";
    }

    private function lockedMessage(User $user): string
    {
        $minutes = max(1, (int) ceil(now()->diffInSeconds($user->locked_until) / 60));

        return "Trop d’essais. Votre compte est bloqué pendant {$minutes} min. Réessayez plus tard ou contactez un responsable.";
    }

    private function fail(string $message): ValidationException
    {
        return ValidationException::withMessages(['login' => $message]);
    }
}
