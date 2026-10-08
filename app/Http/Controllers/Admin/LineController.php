<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Network\RemoveFromNetwork;
use App\Enums\FareType;
use App\Enums\Removal;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LineRequest;
use App\Models\Fare;
use App\Models\Line;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class LineController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Line::class);

        $search = trim((string) $request->string('q'));

        return view('admin.lines.index', [
            'search' => $search,
            'lines' => Line::query()
                ->withCount(['zones', 'stops', 'vehicles'])
                // Line name, code, destination, or one of its zones or boarding points.
                ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('destination_name', 'like', "%{$search}%")
                    ->orWhereHas('zones', fn (Builder $z) => $z->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('stops', fn (Builder $p) => $p->where('stops.name', 'like', "%{$search}%"))))
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Line::class);

        return view('admin.lines.form', ['line' => new Line(['destination_name' => 'Angré', 'is_active' => true])]);
    }

    public function store(LineRequest $request): RedirectResponse
    {
        $line = Line::query()->create($request->validated());

        return redirect()->route('admin.lines.show', $line)->with('success', 'Ligne créée. Ajoutez maintenant ses zones et ses points de montée.');
    }

    public function show(Line $line): View
    {
        Gate::authorize('view', $line);

        $line->load('zones.stops');

        $currentFares = Fare::query()
            ->whereIn('zone_id', $line->zones->pluck('id'))
            ->inForceOn(today())
            ->get()
            ->groupBy(fn (Fare $fare) => $fare->zone_id.'-'.$fare->type->value)
            ->map->first();

        return view('admin.lines.show', [
            'line' => $line,
            'currentFares' => $currentFares,
            'types' => FareType::cases(),
        ]);
    }

    public function edit(Line $line): View
    {
        Gate::authorize('update', $line);

        return view('admin.lines.form', ['line' => $line]);
    }

    public function update(LineRequest $request, Line $line): RedirectResponse
    {
        $line->update($request->validated());

        return redirect()->route('admin.lines.show', $line)->with('success', 'Ligne enregistrée.');
    }

    public function destroy(Line $line, RemoveFromNetwork $remove): RedirectResponse
    {
        Gate::authorize('delete', $line);

        return $remove->line($line) === Removal::Deleted
            ? redirect()->route('admin.lines.index')->with('success', 'Ligne « '.$line->name.' » supprimée avec ses zones, points de montée et prix.')
            : redirect()->route('admin.lines.show', $line)->with('warning', 'Des ventes ont déjà été faites sur cette ligne : elle est archivée (plus vendue) et reste dans l’historique.');
    }
}
