<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Enums\GatewayStatus;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;

/**
 * Development simulator, behaving like a real aggregator:
 * - start(): sends the customer to a local "Mobile Money" page;
 * - the page records the outcome on the "aggregator side" (cache);
 * - check(): reads that outcome, like a status API call;
 * - notifications are signed with HMAC-SHA256(body, PAYMENT_WEBHOOK_SECRET)
 *   in the X-Signature header.
 * Refused in production (PaymentGatewayManager).
 */
class FakePaymentGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'fake';
    }

    public function start(Payment $payment, string $returnUrl, string $notifyUrl): string
    {
        return URL::temporarySignedRoute('payments.fake.show', now()->addMinutes(30), ['payment' => $payment->reference]);
    }

    public function check(Payment $payment): GatewayResult
    {
        /** @var array{status: string, amount: int, currency: string, transaction_id: string}|null $state */
        $state = Cache::get($this->cacheKey($payment->reference));

        if ($state === null) {
            return GatewayResult::waiting('Le client n’a pas encore payé.');
        }

        return new GatewayResult(
            status: GatewayStatus::from($state['status']),
            amount: $state['amount'],
            currency: $state['currency'],
            transactionId: $state['transaction_id'],
            paymentMethod: 'FAKE_MOBILE_MONEY',
            message: $state['status'] === 'refused' ? 'Paiement refusé (simulation).' : null,
            raw: $state,
        );
    }

    /**
     * What the customer chose on the simulator page. $amount lets tests
     * simulate an aggregator reporting a different amount.
     */
    public function record(Payment $payment, bool $accepted, ?int $amount = null, string $currency = 'XOF'): void
    {
        Cache::put($this->cacheKey($payment->reference), [
            'status' => ($accepted ? GatewayStatus::Accepted : GatewayStatus::Refused)->value,
            'amount' => $amount ?? $payment->amount,
            'currency' => $currency,
            'transaction_id' => 'FAKE-'.$payment->reference,
        ], now()->addDay());
    }

    public function verifyNotification(Request $request): bool
    {
        $secret = (string) config('services.payment.webhook_secret');

        if ($secret === '') {
            return false;
        }

        $header = (string) config('services.payment.webhook_signature_header', 'X-Signature');
        $received = strtolower(trim((string) $request->header($header)));

        return $received !== '' && hash_equals(hash_hmac('sha256', $request->getContent(), $secret), $received);
    }

    public function referenceFromNotification(Request $request): ?string
    {
        $reference = $request->json('reference') ?? $request->input('reference');

        return is_string($reference) ? $reference : null;
    }

    public function checkoutOrigin(): ?string
    {
        return null;
    }

    private function cacheKey(string $reference): string
    {
        return "fake-payment:{$reference}";
    }
}
