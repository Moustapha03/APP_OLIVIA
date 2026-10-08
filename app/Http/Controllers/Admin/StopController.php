<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Network\RemoveFromNetwork;
use App\Enums\Removal;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StopRequest;
use App\Models\Line;
use App\Models\Stop;
use App\Models\Zone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Boarding points of a zone (scoped bindings line → zone → stop).
 */
class StopController extends Controller
{
    public function store(StopRequest $request, Line $line, Zone $zone): RedirectResponse
    {
        $stop = new Stop($request->validated());
        $stop->forceFill(['zone_id' => $zone->id])->save();

        return redirect()->route('admin.lines.show', $line)->with('success', 'Point de montée « '.$stop->name.' » ajouté à la zone '.$zone->name.'.');
    }

    public function edit(Line $line, Zone $zone, Stop $stop): View
    {
        Gate::authorize('update', $stop);

        return view('admin.stops.edit', ['line' => $line, 'zone' => $zone, 'stop' => $stop]);
    }

    public function update(StopRequest $request, Line $line, Zone $zone, Stop $stop): RedirectResponse
    {
        $stop->update($request->validated());

        return redirect()->route('admin.lines.show', $line)->with('success', 'Point de montée enregistré.');
    }

    public function destroy(Line $line, Zone $zone, Stop $stop, RemoveFromNetwork $remove): RedirectResponse
    {
        Gate::authorize('delete', $stop);

        return redirect()->route('admin.lines.show', $line)->with(...($remove->stop($stop) === Removal::Deleted
            ? ['success', 'Point de montée « '.$stop->name.' » supprimé.']
            : ['warning', '« '.$stop->name.' » est utilisé par des abonnés ou des reçus : il est archivé (plus proposé) et reste dans l’historique.']));
    }
}
