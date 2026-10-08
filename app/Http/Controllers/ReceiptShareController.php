<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\Receipts\ReceiptSharing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Receipt PDF (download), sending it on WhatsApp, and the signed link the
 * client opens from WhatsApp (no account needed).
 *
 * Staff routes: PaymentPolicy::view (the agent's own sales, payments.view,
 * or the customer's own payment).
 */
class ReceiptShareController extends Controller
{
    public function __construct(private readonly ReceiptSharing $sharing) {}

    /**
     * ?affichage=1: sent inline (fetched by the "Partager le PDF" button).
     */
    public function download(Request $request, Payment $payment): Response
    {
        Gate::authorize('view', $payment);
        abort_unless($this->sharing->canShare($payment), 404);

        return $this->sharing->pdf($payment, inline: $request->boolean('affichage'));
    }

    /**
     * Logged, then sent to WhatsApp with the message ready.
     */
    public function whatsapp(Request $request, Payment $payment): RedirectResponse
    {
        Gate::authorize('view', $payment);
        abort_unless($this->sharing->canShare($payment), 404);

        activity('audit')->causedBy($request->user())->performedOn($payment)->event('receipt.whatsapp')
            ->withProperties(['receipt_number' => $payment->receipt_number])
            ->log('Reçu envoyé par WhatsApp');

        return redirect()->away($this->sharing->whatsappUrl($payment));
    }

    /**
     * Opened by the client from the WhatsApp message (signed, expiring link).
     */
    public function shared(Payment $payment): Response
    {
        // A sale cancelled after the link was sent: the receipt is no longer valid.
        abort_unless($this->sharing->canShare($payment), 410, 'Ce reçu n’est plus valable.');

        return $this->sharing->pdf($payment, inline: true);
    }
}
