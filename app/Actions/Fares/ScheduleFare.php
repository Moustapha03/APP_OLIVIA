<?php

namespace App\Actions\Fares;

use App\Enums\FareType;
use App\Models\Fare;
use App\Models\User;
use App\Models\Zone;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Business rules 1 and 6: a new fare applies from its effective date only.
 *
 * - never in the past: what was sold stays sold at its price;
 * - the fare in force is closed the day before the new one starts;
 * - only one scheduled (future) fare per zone and formula at a time;
 * - fares are never edited: to change a scheduled one, cancel it first.
 *
 * Passes copy their price at purchase, so running subscriptions keep theirs.
 */
class ScheduleFare
{
    /**
     * @throws ValidationException
     */
    public function handle(Zone $zone, FareType $type, int $amount, Carbon $effectiveFrom, ?User $author = null): Fare
    {
        $effectiveFrom = $effectiveFrom->copy()->startOfDay();

        if ($effectiveFrom->isBefore(today())) {
            throw ValidationException::withMessages([
                'effective_from' => 'La date d’effet ne peut pas être dans le passé.',
            ]);
        }

        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Le montant doit être supérieur à 0.']);
        }

        return DB::transaction(function () use ($zone, $type, $amount, $effectiveFrom, $author) {
            // Lock every fare of this zone and formula: two managers saving at
            // the same time cannot create overlapping fares.
            $fares = Fare::query()
                ->where('zone_id', $zone->id)
                ->ofType($type)
                ->orderBy('effective_from')
                ->lockForUpdate()
                ->get();

            $sameDay = $fares->first(fn (Fare $fare) => $fare->effective_from->isSameDay($effectiveFrom));
            if ($sameDay !== null) {
                throw ValidationException::withMessages([
                    'effective_from' => 'Un tarif commence déjà le '.$effectiveFrom->translatedFormat('j F Y').'.',
                ]);
            }

            $later = $fares->first(fn (Fare $fare) => $fare->effective_from->isAfter($effectiveFrom));
            if ($later !== null) {
                throw ValidationException::withMessages([
                    'effective_from' => 'Un tarif de '.Money::format($later->amount).' est déjà programmé le '
                        .$later->effective_from->translatedFormat('j F Y').'. Annulez-le d’abord.',
                ]);
            }

            $current = $fares->last(fn (Fare $fare) => $fare->effective_to === null || $fare->effective_to->gte($effectiveFrom));
            if ($current !== null) {
                $current->effective_to = $effectiveFrom->copy()->subDay();
                $current->save();
            }

            $fare = new Fare;
            $fare->forceFill([
                'zone_id' => $zone->id,
                'type' => $type,
                'amount' => $amount,
                'effective_from' => $effectiveFrom,
                'effective_to' => null,
                'created_by' => $author?->id,
            ])->save();

            return $fare;
        });
    }
}
