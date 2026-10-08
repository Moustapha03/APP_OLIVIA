<?php

namespace App\Http\Controllers\Controle;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The controller's current trip. Trips arrive in step 7.
 */
class TripController extends Controller
{
    public function current(): View
    {
        Gate::authorize('trips.manage-own');

        return view('controle.trips.current');
    }
}
