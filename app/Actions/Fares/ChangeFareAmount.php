<?php

namespace App\Actions\Fares;

use App\Models\Fare;
use Illuminate\Validation\ValidationException;

/**
 * Corrects the amount of the price in force or of a scheduled price.
 * Applies to the next sales only: receipts and passes already sold keep the
 * amount they copied. The old amount stays in the activity log.
 */
class ChangeFareAmount
{
    /**
     * @throws ValidationException
     */
    public function handle(Fare $fare, int $amount): Fare
    {
        if ($fare->effective_to !== null && $fare->effective_to->lt(today())) {
            throw ValidationException::withMessages(['amount' => 'Un ancien prix ne se modifie pas.']);
        }

        $fare->amount = $amount;
        $fare->save();

        return $fare;
    }
}
