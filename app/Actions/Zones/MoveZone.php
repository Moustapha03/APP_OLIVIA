<?php

namespace App\Actions\Zones;

use App\Models\Zone;
use Illuminate\Support\Facades\DB;

/**
 * Swaps a zone with its neighbour (closer to or farther from the
 * destination). The order matters: it defines where a subscription is
 * valid (rule 3: its zone and the closer ones).
 */
class MoveZone
{
    public function handle(Zone $zone, string $direction): void
    {
        DB::transaction(function () use ($zone, $direction) {
            $zones = Zone::query()->where('line_id', $zone->line_id)->orderBy('position')->lockForUpdate()->get();

            $index = $zones->search(fn (Zone $z) => $z->id === $zone->id);
            $neighbourIndex = $direction === 'up' ? $index - 1 : $index + 1;

            if ($index === false || ! isset($zones[$neighbourIndex])) {
                return;
            }

            $current = $zones[$index];
            $neighbour = $zones[$neighbourIndex];
            [$a, $b] = [$current->position, $neighbour->position];

            // Unique (line_id, position): park one zone on a free position first.
            $current->forceFill(['position' => 0])->saveQuietly();
            $neighbour->forceFill(['position' => $a])->save();
            $current->forceFill(['position' => $b])->save();
        });
    }
}
