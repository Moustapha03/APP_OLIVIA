<?php

namespace App\Actions\Payments;

use App\Actions\Passes\EnsureSubscriptionCanBeSold;
use App\Enums\FareType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Stop;
use App\Models\Subscriber;
use App\Models\User;
use App\Services\Payments\PaymentGatewayException;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Step 3 of the online purchase: freezes what is bought and its price,
 * then opens the transaction at the aggregator.
 *
 * The amount comes from the fare of the boarding point's zone in force
 * today (rule 1), NEVER from the form. Nothing is issued here: the pass
 * waits for ConfirmPayment (rule 5).
 */
class StartOnlinePurchase
{
    public function __construct(
        private readonly PaymentGatewayManager $gateways,
        private readonly EnsureSubscriptionCanBeSold $subscriptionRules,
    ) {}

    /**
     * @return array{0: Payment, 1: string} the payment and the URL of the payment page
     *
     * @throws ValidationException
     */
    public function handle(User $customer, Stop $stop, FareType $type, ?Subscriber $subscriber = null, ?Carbon $month = null): array
    {
        if (! $stop->isSellable()) {
            throw ValidationException::withMessages(['stop_id' => 'Ce point de montée n’est plus desservi.']);
        }

        if ($type->isSubscription()) {
            // A customer only buys for the subscribers of their own account.
            if ($subscriber !== null && $subscriber->user_id !== $customer->id) {
                throw ValidationException::withMessages(['subscriber_id' => 'Abonné inconnu.']);
            }

            $this->subscriptionRules->handle($subscriber, $month);
        }

        $fare = $stop->zone->fareOn($type);

        if ($fare === null) {
            throw ValidationException::withMessages(['type' => 'Cette formule n’est pas encore en vente pour cette zone.']);
        }

        $gateway = $this->gateways->driver();

        $payment = new Payment;
        $payment->forceFill([
            // CinetPay accepts letters and digits only in transaction_id.
            'reference' => 'TT'.Str::upper((string) Str::ulid()),
            'user_id' => $customer->id,
            'subscriber_id' => $type->isSubscription() ? $subscriber?->id : null,
            'purpose' => $type,
            'zone_id' => $stop->zone_id,
            'stop_id' => $stop->id,
            'fare_id' => $fare->id,
            'period_month' => $type->isSubscription() ? $month?->copy()->startOfMonth() : null,
            'list_amount' => $fare->amount,
            'amount' => $fare->amount,
            'currency' => (string) config('transport.currency', 'XOF'),
            'gateway' => $gateway->name(),
            'payment_method' => PaymentMethod::Online,
            'status' => PaymentStatus::Pending,
        ])->save();

        try {
            $checkoutUrl = $gateway->start(
                $payment,
                returnUrl: route('espace.payments.show', $payment->reference),
                notifyUrl: route('webhooks.payments', $gateway->name()),
            );
        } catch (PaymentGatewayException $e) {
            report($e);
            $payment->forceFill([
                'status' => PaymentStatus::Failed,
                'failure_reason' => 'gateway_unavailable',
                'failed_at' => now(),
            ])->save();

            throw ValidationException::withMessages([
                'type' => 'Le service de paiement ne répond pas. Réessayez dans quelques minutes ou payez à l’agence.',
            ]);
        }

        activity('payments')->causedBy($customer)->performedOn($payment)
            ->event('payment.started')
            ->withProperties(['reference' => $payment->reference, 'amount' => $payment->amount, 'gateway' => $payment->gateway])
            ->log('Paiement en ligne démarré');

        return [$payment, $checkoutUrl];
    }
}
