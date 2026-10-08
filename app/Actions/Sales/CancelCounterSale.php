<?php

namespace App\Actions\Sales;

use App\Actions\Auth\AuthenticateUser;
use App\Enums\PassStatus;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Business rule 9: a counter sale is cancelled only with a supervisor,
 * present at the desk, who types their own phone number and password.
 *
 * - the supervisor must hold "tickets.cancel", be active, not locked and not
 *   be the agent; anybody else is treated exactly like an unknown number
 *   (same message, same password hashing time): the form never tells
 *   whether a password is right (no password oracle for the agent);
 * - a wrong password counts as a failed login on the supervisor's account
 *   (same lockout as the login form);
 * - every refusal is written to the audit log;
 * - only a sale of the agent's register still OPEN (the cash is in the drawer);
 * - only a pass never used (no ride counted on a ticket);
 * - 5 wrong supervisor passwords per agent and 10 minutes, then blocked.
 * The money handed back leaves the expected register total automatically.
 */
class CancelCounterSale
{
    public const MAX_ATTEMPTS = 5;

    public const REFUSED_MESSAGE = 'Identifiants du superviseur incorrects ou non autorisés.';

    public function __construct(private readonly AuthenticateUser $auth) {}

    /**
     * @throws ValidationException
     */
    public function handle(User $agent, Payment $payment, string $supervisorLogin, string $supervisorPassword, string $reason): Payment
    {
        $limiterKey = 'supervisor-cancel:'.$agent->id;

        if (RateLimiter::tooManyAttempts($limiterKey, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'supervisor_login' => 'Trop d’essais. Réessayez dans '.max(1, (int) ceil(RateLimiter::availableIn($limiterKey) / 60)).' min.',
            ]);
        }

        $supervisor = $this->findSupervisor($agent, $supervisorLogin);

        $passwordOk = $supervisor !== null
            ? Hash::check($supervisorPassword, $supervisor->password)
            : AuthenticateUser::checkAgainstDummy($supervisorPassword);

        if ($supervisor === null || ! $passwordOk) {
            RateLimiter::hit($limiterKey, 600);

            if ($supervisor !== null) {
                $this->auth->registerFailure($supervisor, (string) request()->ip());
            }

            activity('audit')->causedBy($agent)->performedOn($payment)
                ->event('sale.cancel-refused')
                ->withProperties([
                    'reference' => $payment->reference,
                    'supervisor_login' => $supervisorLogin,
                    'supervisor' => $supervisor?->id,
                    'ip' => request()->ip(),
                ])
                ->log('Annulation refusée : validation du superviseur incorrecte');

            throw ValidationException::withMessages(['supervisor_login' => self::REFUSED_MESSAGE]);
        }

        RateLimiter::clear($limiterKey);

        return DB::transaction(function () use ($agent, $payment, $supervisor, $reason) {
            /** @var Payment $locked */
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $locked->load(['cashSession', 'payable']);

            if ($locked->status !== PaymentStatus::Succeeded || $locked->gateway !== 'counter') {
                throw ValidationException::withMessages(['reason' => 'Cette vente ne peut pas être annulée.']);
            }

            if ($locked->sold_by !== $agent->id || $locked->cashSession === null || ! $locked->cashSession->isOpen()) {
                throw ValidationException::withMessages(['reason' => 'Seule une vente de votre caisse ouverte peut être annulée.']);
            }

            $pass = $locked->payable;

            // A ride counted = the pass was used. (Step 7 adds: subscription never scanned.)
            if ($pass instanceof Ticket && $pass->rides_used > 0) {
                throw ValidationException::withMessages(['reason' => 'Ce titre a déjà servi : il ne peut plus être annulé.']);
            }

            $locked->forceFill([
                'status' => PaymentStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => $supervisor->id,
                'cancellation_reason' => $reason,
            ])->save();

            if ($pass instanceof Ticket || $pass instanceof Subscription) {
                $pass->forceFill(['status' => PassStatus::Cancelled])->save();
            }

            activity('cash')->causedBy($supervisor)->performedOn($locked)
                ->event('sale.cancelled')
                ->withProperties([
                    'reference' => $locked->reference,
                    'amount' => $locked->amount,
                    'agent' => $agent->id,
                    'supervisor' => $supervisor->id,
                    'reason' => $reason,
                ])
                ->log('Vente annulée avec validation du superviseur');

            return $locked;
        });
    }

    /**
     * The account typed, only if it may validate a cancellation right now.
     * Anyone else (unknown, the agent, no "tickets.cancel", disabled,
     * locked) is null: indistinguishable from an unknown number.
     */
    private function findSupervisor(User $agent, string $login): ?User
    {
        $user = $this->auth->findUser($login);

        if ($user === null
            || $user->is($agent)
            || ! $user->isActive()
            || $user->isLocked()
            || ! $user->can('tickets.cancel')) {
            return null;
        }

        return $user;
    }
}
