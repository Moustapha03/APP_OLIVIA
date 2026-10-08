<?php

namespace App\Actions\Cash;

use App\Enums\CashSessionStatus;
use App\Models\CashSession;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Closes a register with the cash actually counted.
 * Business rule 8: a difference (missing or extra cash) requires a
 * written justification. The session row is locked: no sale can slip in
 * while closing, and a register cannot be closed twice.
 */
class CloseCashSession
{
    public const MIN_JUSTIFICATION = 10;

    /**
     * @throws ValidationException
     */
    public function handle(User $agent, CashSession $session, int $counted, ?string $justification): CashSession
    {
        return DB::transaction(function () use ($agent, $session, $counted, $justification) {
            /** @var CashSession $locked */
            $locked = CashSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isOpen()) {
                throw ValidationException::withMessages(['counted_amount' => 'Cette caisse est déjà clôturée.']);
            }

            $expected = CashSessionTotals::for($locked)->expected();
            $difference = $counted - $expected;
            $justification = trim((string) $justification);

            if ($difference !== 0 && mb_strlen($justification) < self::MIN_JUSTIFICATION) {
                throw ValidationException::withMessages([
                    'justification' => ($difference < 0 ? 'Il manque ' : 'Il y a ').Money::format(abs($difference))
                        .($difference < 0 ? '' : ' en trop').'. Expliquez l’écart pour pouvoir clôturer.',
                ]);
            }

            $locked->forceFill([
                'status' => CashSessionStatus::Closed,
                'closed_at' => now(),
                'expected_amount' => $expected,
                'counted_amount' => $counted,
                'difference' => $difference,
                'justification' => $justification !== '' ? $justification : null,
            ])->save();

            activity('cash')->causedBy($agent)->performedOn($locked)
                ->event('cash.closed')
                ->withProperties(['expected' => $expected, 'counted' => $counted, 'difference' => $difference, 'justification' => $locked->justification])
                ->log($difference === 0 ? 'Clôture de caisse' : 'Clôture de caisse avec écart');

            return $locked;
        });
    }
}
