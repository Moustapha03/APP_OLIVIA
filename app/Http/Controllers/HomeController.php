<?php

namespace App\Http\Controllers;

use App\Enums\FareType;
use App\Models\Fare;
use App\Models\Line;
use App\Models\Zone;
use Illuminate\View\View;

/**
 * Public home page: the price grid of the day, zone by zone, with the
 * boarding points of each zone.
 */
class HomeController extends Controller
{
    public function __invoke(): View
    {
        $today = today();

        $lines = Line::query()
            ->active()
            ->with(['zones' => fn ($q) => $q->active()->with([
                'stops' => fn ($s) => $s->active(),
                'fares' => fn ($f) => $f->inForceOn($today),
            ])])
            ->orderBy('name')
            ->get();

        $grid = $lines->map(fn (Line $line) => [
            'line' => $line,
            'zones' => $line->zones
                ->map(fn (Zone $zone) => [
                    'zone' => $zone,
                    'stops' => $zone->stops->pluck('name')->all(),
                    'prices' => collect(FareType::cases())
                        ->mapWithKeys(fn (FareType $type) => [$type->value => $zone->fares->first(fn (Fare $f) => $f->type === $type)?->amount])
                        ->filter(fn (?int $amount) => $amount !== null)
                        ->all(),
                ])
                // A zone without any price cannot be sold yet: not shown.
                ->filter(fn (array $row) => $row['prices'] !== [])
                ->sortByDesc(fn (array $row) => $row['zone']->position)
                ->values(),
        ])->filter(fn (array $row) => $row['zones']->isNotEmpty())->values();

        return view('public.home', [
            'grid' => $grid,
            'types' => FareType::cases(),
            'dailyRides' => (int) config('transport.tickets.daily_rides'),
        ]);
    }
}
