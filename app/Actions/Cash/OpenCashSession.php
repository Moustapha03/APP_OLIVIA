<?php

namespace App\Actions\Cash;

use App\Enums\CashSessionStatus;
use App\Models\CashSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Opens the agent's cash register with the float counted in the drawer.
 * One open register per agent: the user row is locked so two clicks (or
 * two tabs) cannot open two registers.
 */
class OpenCashSession
{
    /**
     * @throws ValidationException
     */
    public function handle(User $agent, int $openingFloat): CashSession
    {
        return DB::transaction(function () use ($agent, $openingFloat) {
            User::query()->whereKey($agent->id)->lockForUpdate()->first();

            if ($agent->openCashSession()->exists()) {
                throw ValidationException::withMessages(['opening_float' => 'Votre caisse est déjà ouverte.']);
            }

            $session = new CashSession(['opening_float' => $openingFloat, 'opened_at' => now()]);
            $session->forceFill(['user_id' => $agent->id, 'status' => CashSessionStatus::Open])->save();

            activity('cash')->causedBy($agent)->performedOn($session)
                ->event('cash.opened')
                ->withProperties(['opening_float' => $openingFloat])
                ->log('Ouverture de caisse');

            return $session;
        });
    }
}
