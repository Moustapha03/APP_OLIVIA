<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessPaymentNotification;
use App\Models\Payment;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Signed notification (VerifyWebhookSignature already passed): answer
 * fast, verify on the queue. Unknown references are acknowledged too, so
 * the aggregator stops retrying, and logged.
 */
class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, string $gateway, PaymentGatewayManager $gateways): JsonResponse
    {
        $reference = $gateways->driver($gateway)->referenceFromNotification($request);

        $payment = $reference === null ? null : Payment::query()
            ->where('reference', $reference)
            ->where('gateway', $gateway)
            ->first();

        activity('payments')
            ->event('webhook.received')
            ->withProperties(['gateway' => $gateway, 'reference' => $reference, 'known' => $payment !== null, 'ip' => $request->ip()])
            ->log('Notification de paiement reçue');

        if ($payment !== null && $payment->isPending()) {
            ProcessPaymentNotification::dispatch($payment->id);
        }

        return response()->json(['status' => 'received']);
    }
}
