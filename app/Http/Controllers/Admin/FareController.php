<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Fares\ChangeFareAmount;
use App\Actions\Fares\RemoveFare;
use App\Actions\Fares\ScheduleFare;
use App\Enums\FareType;
use App\Enums\Removal;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreFareRequest;
use App\Models\Fare;
use App\Models\Line;
use App\Models\Zone;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Price grid: one price per zone and formula, from a date.
 */
class FareController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Fare::class);

        // Search: a zone or one of its boarding points.
        $search = trim((string) $request->string('q'));
        $matches = fn ($zones) => $zones->where(fn ($w) => $w->where('name', 'like', "%{$search}%")
            ->orWhereHas('stops', fn ($p) => $p->where('name', 'like', "%{$search}%")));

        $lines = Line::query()
            ->when($search !== '', fn ($q) => $q->whereHas('zones', $matches))
            ->with([
                'zones' => fn ($zones) => $search !== '' ? $matches($zones) : $zones,
                'zones.stops',
                'zones.fares' => fn ($query) => $query->orderByDesc('effective_from'),
            ])
            ->orderBy('name')
            ->get();

        return view('admin.fares.index', [
            'search' => $search,
            'lines' => $lines,
            'types' => FareType::cases(),
            'today' => today(),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Fare::class);

        $zones = Zone::query()->with('line')->orderBy('line_id')->orderBy('position')->get();

        return view('admin.fares.create', [
            'zoneOptions' => $zones->mapWithKeys(fn (Zone $zone) => [$zone->id => $zone->line->name.' · '.$zone->name])->all(),
            'selectedZone' => $request->integer('zone') ?: null,
            'selectedType' => (FareType::tryFrom((string) $request->query('type')) ?? FareType::Daily)->value,
            'types' => FareType::cases(),
        ]);
    }

    public function store(StoreFareRequest $request, ScheduleFare $scheduleFare): RedirectResponse
    {
        $zone = Zone::query()->findOrFail($request->validated('zone_id'));
        $type = FareType::from($request->validated('type'));
        $from = Carbon::createFromFormat('Y-m-d', $request->validated('effective_from'))->startOfDay();

        $fare = $scheduleFare->handle($zone, $type, (int) $request->validated('amount'), $from, $request->user());

        $when = $fare->effective_from->isToday() ? 'dès aujourd’hui' : 'à partir du '.$fare->effective_from->translatedFormat('j F Y');

        return redirect()->route('admin.fares.index')->with(
            'success',
            "{$type->label()} · {$zone->name} : ".Money::format($fare->amount)." {$when}. Les titres déjà vendus gardent leur prix."
        );
    }

    /**
     * Corrects the price in force (or a scheduled one): next sales only.
     */
    public function update(Request $request, Fare $fare, ChangeFareAmount $change): RedirectResponse
    {
        Gate::authorize('update', $fare);

        if (is_string($request->input('amount'))) {
            $request->merge(['amount' => preg_replace('/\D+/', '', $request->input('amount'))]);
        }

        $amount = (int) $request->validate([
            'amount' => ['required', 'integer', 'min:1', 'max:'.StoreFareRequest::MAX_AMOUNT],
        ], [
            'amount.required' => 'Indiquez le prix.',
            'amount.min' => 'Le prix doit être supérieur à 0.',
            'amount.max' => 'Ce prix semble trop élevé. Vérifiez le nombre de zéros.',
        ])['amount'];

        $old = $fare->amount;
        $change->handle($fare, $amount);

        return redirect()->route('admin.fares.index')->with(
            'success',
            "{$fare->type->label()} · {$fare->zone->name} : ".Money::format($old).' → '.Money::format($amount).'. Les reçus déjà faits gardent leur montant.'
        );
    }

    public function destroy(Fare $fare, RemoveFare $remove): RedirectResponse
    {
        Gate::authorize('delete', $fare);

        $label = "{$fare->type->label()} · {$fare->zone->name}";

        return redirect()->route('admin.fares.index')->with(...($remove->handle($fare) === Removal::Deleted
            ? ['success', "Prix supprimé : {$label}."]
            : ['warning', "{$label} : ce prix a déjà servi à des ventes. Il reste dans l’historique mais ne s’applique plus : la formule n’est plus vendue dans cette zone tant qu’un nouveau prix n’est pas fixé."]));
    }
}
