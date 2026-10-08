<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessPaymentNotification;
use App\Models\Payment;
use App\Services\Payments\FakePaymentGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Simulated Mobile Money page (driver "fake", never in production — the
 * routes are not even registered there). Like a real aggregator: records
 * the outcome on its side, notifies us, and sends the customer back.
 */
class FakePaymentController extends Controller
{
    public function show(Request $request, Payment $payment): View
    {
        Gate::authorize('view', $payment);
        abort_unless($payment->gateway === 'fake', 404);

        return view('payments.fake', [
            'payment' => $payment->load('stop'),
            'decideUrl' => $request->fullUrl(),
        ]);
    }

    public function decide(Request $request, Payment $payment, FakePaymentGateway $gateway): RedirectResponse
    {
        Gate::authorize('view', $payment);
        abort_unless($payment->gateway === 'fake' && $payment->isPending(), 404);

        $accepted = $request->validate(['decision' => ['required', 'in:accept,refuse']])['decision'] === 'accept';

        $gateway->record($payment, $accepted);

        // What the aggregator's notification would trigger.
        ProcessPaymentNotification::dispatch($payment->id);

        return redirect()->route('espace.payments.show', $payment->reference);
    }
}
