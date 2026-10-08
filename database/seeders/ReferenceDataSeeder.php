<?php

namespace Database\Seeders;

use App\Enums\FareType;
use App\Models\Fare;
use App\Models\Line;
use App\Models\Stop;
use App\Models\Zone;
use Illuminate\Database\Seeder;

/**
 * The agency's network and price grid (documents given by the client):
 *
 *   Koumassi / Marcory → Angré           mensuel 30 000 · jour   500
 *   Anani / Gonzague / Port-Bouët → Angré mensuel 40 000 · jour 1 000
 *   Bonoua / Bassam → Angré              mensuel 50 000 · jour 1 500
 *
 * "Matin simple" / "Soir simple": 30 000 seen on receipts for Bonoua/Bassam
 * (matin and soir) and Carrefour Aéroport (matin). Not known yet for
 * Koumassi / Marcory: left empty, to be set in Admin → Tarifs.
 * Boarding points: those of the receipts history + the grid.
 *
 * Idempotent: never overwrites data edited in the back office.
 */
class ReferenceDataSeeder extends Seeder
{
    /**
     * position (1 = closest to Angré) => [zone name, boarding points, fares]
     *
     * @var array<int, array{string, list<string>, array<string, int>}>
     */
    private const ZONES = [
        1 => ['Koumassi / Marcory', ['Koumassi', 'Marcory'], [
            'daily' => 500, 'monthly' => 30000,
        ]],
        2 => ['Anani / Gonzague / Port-Bouët', ['Anani', 'Gonzague', 'Port-Bouët', 'Carrefour Aéroport'], [
            'daily' => 1000, 'monthly' => 40000, 'monthly_morning' => 30000, 'monthly_evening' => 30000,
        ]],
        3 => ['Bonoua / Bassam', ['Bonoua', 'Grand-Bassam', 'Moossou', 'Zouglou', 'Brique', 'Yaou', 'Carrefour Hôpital Yaou', 'Gravier 8 Kilo Yaou'], [
            'daily' => 1500, 'monthly' => 50000, 'monthly_morning' => 30000, 'monthly_evening' => 30000,
        ]],
    ];

    public function run(): void
    {
        $line = Line::query()->firstOrCreate(
            ['code' => 'ANGRE'],
            ['name' => 'Bonoua – Angré', 'destination_name' => 'Angré', 'is_active' => true],
        );

        foreach (self::ZONES as $position => [$zoneName, $stops, $fares]) {
            $zone = Zone::query()->where('line_id', $line->id)->where('name', $zoneName)->first();

            if ($zone === null) {
                $zone = new Zone(['name' => $zoneName, 'is_active' => true]);
                $zone->forceFill(['line_id' => $line->id, 'position' => $position])->save();
            }

            foreach ($stops as $stopName) {
                if (! Stop::query()->where('zone_id', $zone->id)->where('name', $stopName)->exists()) {
                    $stop = new Stop(['name' => $stopName, 'is_active' => true]);
                    $stop->forceFill(['zone_id' => $zone->id])->save();
                }
            }

            foreach ($fares as $type => $amount) {
                if (! Fare::query()->where('zone_id', $zone->id)->ofType(FareType::from($type))->exists()) {
                    (new Fare)->forceFill([
                        'zone_id' => $zone->id,
                        'type' => FareType::from($type),
                        'amount' => $amount,
                        // From the start of the year: the imported history (September, October) is priced too.
                        'effective_from' => today()->startOfYear(),
                    ])->save();
                }
            }
        }
    }
}
