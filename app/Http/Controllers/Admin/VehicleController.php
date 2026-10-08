<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Network\RemoveFromNetwork;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\VehicleRequest;
use App\Models\Line;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class VehicleController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Vehicle::class);

        $search = trim((string) $request->string('q'));
        $status = $request->query('statut');

        return view('admin.vehicles.index', [
            'search' => $search,
            'status' => is_string($status) ? $status : '',
            'vehicles' => Vehicle::query()
                ->with('line')
                ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('registration', 'like', "%{$search}%")))
                ->when($status === 'service', fn (Builder $q) => $q->where('is_active', true))
                ->when($status === 'hors-service', fn (Builder $q) => $q->where('is_active', false))
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Vehicle::class);

        return view('admin.vehicles.form', [
            'vehicle' => new Vehicle(['capacity' => 70, 'is_active' => true]),
            'lineOptions' => $this->lineOptions(),
        ]);
    }

    public function store(VehicleRequest $request): RedirectResponse
    {
        Vehicle::query()->create($request->validated());

        return redirect()->route('admin.vehicles.index')->with('success', 'Véhicule ajouté.');
    }

    public function edit(Vehicle $vehicle): View
    {
        Gate::authorize('update', $vehicle);

        return view('admin.vehicles.form', [
            'vehicle' => $vehicle,
            'lineOptions' => $this->lineOptions(),
        ]);
    }

    public function update(VehicleRequest $request, Vehicle $vehicle): RedirectResponse
    {
        $vehicle->update($request->validated());

        return redirect()->route('admin.vehicles.index')->with('success', 'Véhicule enregistré.');
    }

    /**
     * @return array<int|string, string>
     */
    private function lineOptions(): array
    {
        return ['' => 'Aucune ligne fixe'] + Line::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function destroy(Vehicle $vehicle, RemoveFromNetwork $remove): RedirectResponse
    {
        Gate::authorize('delete', $vehicle);

        $remove->vehicle($vehicle);

        return redirect()->route('admin.vehicles.index')->with('success', 'Véhicule « '.$vehicle->name.' » supprimé.');
    }
}
