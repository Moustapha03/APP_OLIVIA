<?php

namespace App\Http\Controllers\Espace;

use App\Actions\Payments\ConfirmPayment;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Throwable;

/**
 * Return from the payment page. Coming back proves nothing: the status
 * is asked to the aggregator API (ConfirmPayment), as for a webhook.
 */
class PaymentController extends Controller
{
    public function show(Payment $payment, ConfirmPayment $confirm): View|RedirectResponse
    {
        Gate::authorize('view', $payment);

        if ($payment->isPending()) {
            try {
                $payment = $confirm->handle($payment);
            } catch (Throwable $e) {
                report($e); // the webhook job will try again
            }
        }

        if ($payment->isSucceeded() && $payment->payable !== null) {
            $route = $payment->payable instanceof Subscription
                ? route('espace.subscriptions.show', $payment->payable->uuid)
                : route('espace.tickets.show', $payment->payable->uuid);

            return redirect($route)->with('success', 'Paiement reçu. Votre titre est prêt : montrez ce QR code au contrôleur.');
        }

        return view('espace.payments.show', ['payment' => $payment->load(['stop', 'zone', 'subscriber'])]);
    }
}
