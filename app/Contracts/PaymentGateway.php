<?php

namespace App\Contracts;

use App\Models\Payment;
use App\Services\Payments\GatewayResult;
use App\Services\Payments\PaymentGatewayException;
use Illuminate\Http\Request;

/**
 * A Mobile Money aggregator (CinetPay, PayDunya, Wave…).
 *
 * Never trust the browser: the pass is issued only after check() — a
 * server-to-server call — says the transaction is accepted for the
 * expected amount and currency (App\Actions\Payments\ConfirmPayment).
 */
interface PaymentGateway
{
    public function name(): string;

    /**
     * Opens the transaction at the aggregator and returns the URL of its
     * payment page, where the customer is redirected.
     *
     * @throws PaymentGatewayException
     */
    public function start(Payment $payment, string $returnUrl, string $notifyUrl): string;

    /**
     * Asks the aggregator API for the real status of the transaction.
     * A network error is reported as "waiting" (checked again later).
     */
    public function check(Payment $payment): GatewayResult;

    /**
     * Is this notification really sent by the aggregator (signature)?
     */
    public function verifyNotification(Request $request): bool;

    /**
     * Our payment reference, read from a notification.
     */
    public function referenceFromNotification(Request $request): ?string;

    /**
     * Origin of the payment page, allowed in the CSP form-action
     * (the purchase form redirects there). Null = our own site.
     */
    public function checkoutOrigin(): ?string;
}
