<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Network\RemoveFromNetwork;
use App\Actions\Zones\MoveZone;
use App\Enums\Removal;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ZoneRequest;
use App\Models\Line;
use App\Models\Zone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Fare zones of a line (scoped bindings: a zone of another line = 404).
 */
class ZoneController extends Controller
{
    public function store(ZoneRequest $request, Line $line): RedirectResponse
    {
        DB::transaction(function () use ($request, $line) {
            Line::query()->whereKey($line->id)->lockForUpdate()->first();

            $zone = new Zone($request->validated());
            $zone->forceFill(['line_id' => $line->id, 'position' => $line->nextZonePosition()])->save();
        });

        return redirect()->route('admin.lines.show', $line)->with('success', 'Zone ajoutée (la plus loin de la destination). Ajoutez ses points de montée et ses tarifs.');
    }

    public function edit(Line $line, Zone $zone): View
    {
        Gate::authorize('stops.manage');

        return view('admin.zones.edit', ['line' => $line, 'zone' => $zone]);
    }

    public function update(ZoneRequest $request, Line $line, Zone $zone): RedirectResponse
    {
        $zone->update($request->validated());

        return redirect()->route('admin.lines.show', $line)->with('success', 'Zone enregistrée.');
    }

    public function move(Request $request, Line $line, Zone $zone, MoveZone $moveZone): RedirectResponse
    {
        Gate::authorize('stops.manage');

        $direction = $request->validate(['direction' => ['required', 'in:up,down']])['direction'];
        $moveZone->handle($zone, $direction);

        return redirect()->route('admin.lines.show', $line)->with('success', 'Ordre des zones modifié.');
    }

    public function destroy(Line $line, Zone $zone, RemoveFromNetwork $remove): RedirectResponse
    {
        Gate::authorize('stops.manage');

        return redirect()->route('admin.lines.show', $line)->with(...($remove->zone($zone) === Removal::Deleted
            ? ['success', 'Zone « '.$zone->name.' » supprimée avec ses points de montée et ses prix.']
            : ['warning', 'Des ventes ont déjà été faites dans la zone « '.$zone->name.' » : elle est archivée (plus vendue) et reste dans l’historique.']));
    }
}
