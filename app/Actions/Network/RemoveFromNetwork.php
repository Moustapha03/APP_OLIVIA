<?php

namespace App\Actions\Network;

use App\Enums\Removal;
use App\Models\Fare;
use App\Models\Line;
use App\Models\Payment;
use App\Models\Stop;
use App\Models\Subscriber;
use App\Models\Subscription;
use App\Models\Ticket;
use App\Models\Vehicle;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * "Supprimer" a line, a zone, a boarding point or a vehicle.
 *
 * Never used (no sale, receipt, pass or subscriber refers to it): really
 * deleted, with its zones, points and prices. Used: archived (inactive,
 * no longer sold) so that receipts and history stay readable.
 */
class RemoveFromNetwork
{
    public function line(Line $line): Removal
    {
        return DB::transaction(function () use ($line) {
            $zoneIds = $line->zones()->pluck('id')->all();

            if ($this->zonesUsed($zoneIds)) {
                $line->update(['is_active' => false]);

                return Removal::Archived;
            }

            Fare::query()->whereIn('zone_id', $zoneIds)->delete();
            Stop::query()->whereIn('zone_id', $zoneIds)->delete();
            Zone::query()->whereIn('id', $zoneIds)->delete();
            $line->delete();

            return Removal::Deleted;
        });
    }

    public function zone(Zone $zone): Removal
    {
        return DB::transaction(function () use ($zone) {
            Line::query()->whereKey($zone->line_id)->lockForUpdate()->first();

            if ($this->zonesUsed([$zone->id])) {
                $zone->update(['is_active' => false]);

                return Removal::Archived;
            }

            Fare::query()->where('zone_id', $zone->id)->delete();
            Stop::query()->where('zone_id', $zone->id)->delete();
            $zone->delete();

            // Close the gap in the order (positions are unique per line).
            Zone::query()->where('line_id', $zone->line_id)->where('position', '>', $zone->position)
                ->orderBy('position')->get()
                ->each(fn (Zone $next) => $next->forceFill(['position' => $next->position - 1])->save());

            return Removal::Deleted;
        });
    }

    public function stop(Stop $stop): Removal
    {
        return DB::transaction(function () use ($stop) {
            if ($this->stopsUsed([$stop->id])) {
                $stop->update(['is_active' => false]);

                return Removal::Archived;
            }

            $stop->delete();

            return Removal::Deleted;
        });
    }

    /**
     * Vehicles are not referred to by sales: always deleted.
     */
    public function vehicle(Vehicle $vehicle): Removal
    {
        $vehicle->delete();

        return Removal::Deleted;
    }

    /**
     * @param  list<int>  $zoneIds
     */
    private function zonesUsed(array $zoneIds): bool
    {
        if ($zoneIds === []) {
            return false;
        }

        $inZone = fn (string $model) => $model::query()->whereIn('zone_id', $zoneIds)->exists();

        return $inZone(Payment::class) || $inZone(Ticket::class) || $inZone(Subscription::class)
            || $this->stopsUsed(Stop::query()->whereIn('zone_id', $zoneIds)->pluck('id')->all());
    }

    /**
     * @param  list<int>  $stopIds
     */
    private function stopsUsed(array $stopIds): bool
    {
        if ($stopIds === []) {
            return false;
        }

        $atStop = fn (Builder $query) => $query->withoutGlobalScopes()->whereIn('stop_id', $stopIds)->exists();

        return $atStop(Payment::query()) || $atStop(Ticket::query()) || $atStop(Subscription::query())
            || $atStop(Subscriber::query());
    }
}
