<?php

namespace App\Support;

use App\Models\Line;

/**
 * Active boarding points, farthest zone first, for <select> lists:
 * [stop id => "Zouglou · Bonoua / Bassam"].
 */
final class BoardingPoints
{
    /**
     * @return array<int, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (Line::query()->active()->with('zones.stops')->orderBy('name')->get() as $line) {
            foreach ($line->zones->where('is_active', true)->sortByDesc('position') as $zone) {
                foreach ($zone->stops->where('is_active', true) as $stop) {
                    $options[$stop->id] = $stop->name.' · '.$zone->name;
                }
            }
        }

        return $options;
    }
}
