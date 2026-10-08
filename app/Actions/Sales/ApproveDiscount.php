<?php

namespace App\Actions\Sales;

use App\Enums\UserStatus;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * A price below the grid at the desk is validated on the spot by a manager
 * holding "sales.discount" (phone or e-mail + password), never by the agent
 * who takes the money. Same message for every failure (no password oracle),
 * 5 tries per 10 minutes per agent, refusals logged.
 */
class ApproveDiscount
{
    private const MAX_TRIES = 5;

    private const DECAY_SECONDS = 600;

    /**
     * @throws ValidationException
     */
    public function handle(User $agent, string $login, string $password): User
    {
        $key = 'discount-approval:'.$agent->id;

        if (RateLimiter::tooManyAttempts($key, self::MAX_TRIES)) {
            throw ValidationException::withMessages([
                'approver_login' => 'Trop d’essais. Réessayez dans '.max(1, (int) ceil(RateLimiter::availableIn($key) / 60)).' min.',
            ]);
        }

        $phone = PhoneNumber::normalize($login);
        $approver = User::query()
            ->where($phone !== null ? 'phone' : 'email', $phone ?? mb_strtolower(trim($login)))
            ->first();

        $allowed = $approver !== null
            && $approver->id !== $agent->id
            && $approver->status === UserStatus::Active
            && $approver->can('sales.discount');

        // Constant work whether the account exists or not.
        $passwordOk = Hash::check($password, $allowed ? $approver->password : '$2y$12$'.str_repeat('a', 53));

        if (! $allowed || ! $passwordOk) {
            RateLimiter::hit($key, self::DECAY_SECONDS);
            activity('audit')->causedBy($agent)->event('sale.discount.refused')
                ->withProperties(['login' => $login])
                ->log('Validation de prix refusée');

            throw ValidationException::withMessages([
                'approver_login' => 'Validation refusée : identifiants incorrects ou personne non autorisée à accorder un prix.',
            ]);
        }

        RateLimiter::clear($key);

        return $approver;
    }
}
