<?php

namespace App\Actions\Payments;

use App\Actions\Passes\IssuePass;
use App\Enums\GatewayStatus;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\Payments\GatewayResult;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Receipts\ReceiptNumbers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The ONLY place where an online payment becomes a pass (rule 5).
 *
 * Called by the queued webhook job and when the customer comes back from
 * the payment page. Both are safe to repeat:
 * 1. ask the aggregator API for the real status (server to server);
 * 2. lock the payment row; if it is no longer pending, stop (idempotent);
 * 3. accepted AND same amount AND same currency → paid + pass issued;
 *    refused → failed; still waiting → unchanged.
 * A payments.payment_id unique index backs this up at database level.
 */
class ConfirmPayment
{
    public function __construct(
        private readonly PaymentGatewayManager $gateways,
        private readonly IssuePass $issuePass,
        private readonly ReceiptNumbers $receipts,
    ) {}

    public function handle(Payment $payment): Payment
    {
        if (! $payment->isPending()) {
            return $payment;
        }

        // Outside the transaction: never hold a row lock during an HTTP call.
        $result = $this->gateways->driver($payment->gateway)->check($payment);

        return DB::transaction(function () use ($payment, $result) {
            /** @var Payment $locked */
            $locked = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->isPending()) {
                return $locked;
            }

            $locked->payload = $result->raw;

            match ($result->status) {
                GatewayStatus::Accepted => $this->accept($locked, $result),
                GatewayStatus::Refused => $this->fail($locked, 'refused', $result->message),
                GatewayStatus::Waiting => $locked->save(),
            };

            return $locked;
        });
    }

    private function accept(Payment $payment, GatewayResult $result): void
    {
        if ($result->amount !== $payment->amount || strtoupper((string) $result->currency) !== $payment->currency) {
            Log::critical('Payment amount or currency mismatch', [
                'reference' => $payment->reference,
                'expected' => [$payment->amount, $payment->currency],
                'reported' => [$result->amount, $result->currency],
            ]);

            $this->fail($payment, 'amount_mismatch', 'Montant ou devise différents de la commande.');

            return;
        }

        $payment->forceFill([
            'status' => PaymentStatus::Succeeded,
            'gateway_transaction_id' => $result->transactionId,
            // The aggregator's method (OMCIV2, WAVECI…) is kept in gateway_transaction_id/payload.
            'receipt_number' => $this->receipts->next($payment->purpose),
            'confirmed_at' => now(),
        ])->save();

        $pass = $this->issuePass->handle($payment);

        activity('payments')->performedOn($payment)
            ->event('payment.succeeded')
            ->withProperties(['reference' => $payment->reference, 'amount' => $payment->amount, 'pass' => $pass->passUuid()])
            ->log('Paiement confirmé, titre émis');
    }

    private function fail(Payment $payment, string $reason, ?string $message): void
    {
        $payment->forceFill([
            'status' => PaymentStatus::Failed,
            'failure_reason' => $reason,
            'failed_at' => now(),
        ])->save();

        activity('payments')->performedOn($payment)
            ->event('payment.failed')
            ->withProperties(['reference' => $payment->reference, 'reason' => $reason, 'message' => $message])
            ->log('Paiement échoué');
    }
}
