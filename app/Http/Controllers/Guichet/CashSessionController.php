<?php

namespace App\Http\Controllers\Guichet;

use App\Actions\Cash\CashSessionTotals;
use App\Actions\Cash\CloseCashSession;
use App\Actions\Cash\OpenCashSession;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guichet\CloseCashSessionRequest;
use App\Http\Requests\Guichet\OpenCashSessionRequest;
use App\Models\CashSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The agent's own register. Opening and closing act on the logged-in
 * agent's register only: no register id in those URLs, so no way to touch
 * someone else's. The closing report is checked with the policy.
 */
class CashSessionController extends Controller
{
    public function show(Request $request): View
    {
        Gate::authorize('cash.open');

        $user = $request->user();
        abort_if($user === null, 403);

        $cashSession = $user->openCashSession()->first();

        if ($cashSession !== null) {
            Gate::authorize('view', $cashSession);
        }

        return view('guichet.cash.show', [
            'cashSession' => $cashSession,
            'totals' => $cashSession !== null ? CashSessionTotals::for($cashSession) : null,
            'lastClosed' => $user->cashSessions()->where('status', 'closed')->latest('closed_at')->first(),
        ]);
    }

    public function open(OpenCashSessionRequest $request, OpenCashSession $open): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $open->handle($user, (int) $request->validated('opening_float'));

        return redirect()->route('guichet.subscriptions.create')->with('success', 'Caisse ouverte. Vous pouvez vendre.');
    }

    public function close(CloseCashSessionRequest $request, CloseCashSession $close): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $cashSession = $user->openCashSession()->first();

        if ($cashSession === null) {
            return redirect()->route('guichet.cash.show')->with('warning', 'Votre caisse est déjà fermée.');
        }

        Gate::authorize('close', $cashSession);

        $closed = $close->handle($user, $cashSession, (int) $request->validated('counted_amount'), $request->validated('justification'));

        return redirect()->route('guichet.cash.report', $closed)->with('success', 'Caisse clôturée. Imprimez le rapport et remettez-le avec l’argent.');
    }

    /**
     * Printable closing report.
     */
    public function report(CashSession $cashSession): View
    {
        Gate::authorize('view', $cashSession);

        return view('guichet.cash.report', [
            'cashSession' => $cashSession->load('user'),
            'totals' => CashSessionTotals::for($cashSession),
            'sales' => $cashSession->payments()->with(['zone', 'subscriber'])->orderBy('created_at')->get(),
            'width' => (int) config('transport.counter.receipt_width', 80),
        ]);
    }
}
