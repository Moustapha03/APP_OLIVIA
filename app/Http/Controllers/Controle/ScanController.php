<?php

namespace App\Http\Controllers\Controle;

use App\Actions\Scans\VerifyPass;
use App\Http\Controllers\Controller;
use App\Http\Requests\Controle\ScanRequest;
use App\Models\Scan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Pass checks on board: camera scanner (or receipt number typed by hand),
 * full-screen verdict, and the controller's own scans of the day.
 * Offline mode (PWA) arrives later in step 7.
 */
class ScanController extends Controller
{
    public function create(): View
    {
        Gate::authorize('scans.create');

        return view('controle.scan');
    }

    /**
     * The verdict is rendered directly (no redirect): one tap on
     * "Scanner le suivant" goes back to the camera.
     */
    public function store(ScanRequest $request, VerifyPass $verify): View
    {
        $check = $verify->handle($request->user(), $request->string('code')->toString());

        return view('controle.scan-result', ['check' => $check]);
    }

    public function index(Request $request): View
    {
        Gate::authorize('scans.create');

        $scans = Scan::query()
            ->where('user_id', $request->user()->id)
            ->where('scanned_at', '>=', today())
            ->with(['subscription.subscriber', 'ticket'])
            ->latest('scanned_at')
            ->latest('id')
            ->limit(50)
            ->get();

        return view('controle.scans.index', ['scans' => $scans]);
    }
}
