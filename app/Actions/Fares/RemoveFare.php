<?php

namespace App\Actions\Fares;

use App\Enums\Removal;
use App\Models\Fare;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * "Supprimer" a price.
 *
 * - scheduled, or never used for a sale: deleted, and the previous price of
 *   the same zone and formula is reopened when this one was the latest;
 * - in force and already used: ended yesterday (kept for history), so the
 *   formula is no longer sold in that zone until a new price is set;
 * - an old price already used: part of history, never removed.
 */
class RemoveFare
{
    /**
     * @throws ValidationException
     */
    public function handle(Fare $fare): Removal
    {
        return DB::transaction(function () use ($fare) {
            /** @var Fare $locked */
            $locked = Fare::query()->whereKey($fare->getKey())->lockForUpdate()->firstOrFail();

            $used = Payment::query()->where('fare_id', $locked->id)->exists()
                || Ticket::query()->where('fare_id', $locked->id)->exists()
                || Subscription::query()->where('fare_id', $locked->id)->exists();

            $latest = Fare::query()
                ->where('zone_id', $locked->zone_id)
                ->ofType($locked->type)
                ->whereDate('effective_from', '>', $locked->effective_from->toDateString())
                ->doesntExist();

            if ($used && ! $locked->isScheduled()) {
                if (! $latest || ($locked->effective_to !== null && $locked->effective_to->lt(today()))) {
                    throw ValidationException::withMessages([
                        'fare' => 'Cet ancien prix a servi à des ventes : il reste dans l’historique.',
                    ]);
                }

                $locked->effective_to = today()->subDay();
                $locked->save();

                return Removal::Archived;
            }

            $previous = Fare::query()
                ->where('zone_id', $locked->zone_id)
                ->ofType($locked->type)
                ->whereDate('effective_from', '<', $locked->effective_from->toDateString())
                ->orderByDesc('effective_from')
                ->lockForUpdate()
                ->first();

            $locked->delete();

            if ($latest && $previous !== null) {
                $previous->effective_to = null;
                $previous->save();
            }

            return Removal::Deleted;
        });
    }
}
