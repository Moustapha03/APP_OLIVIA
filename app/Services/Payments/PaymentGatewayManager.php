<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use InvalidArgumentException;

/**
 * Resolves the aggregator driver (config services.payment.driver).
 * Adding PayDunya or Wave = one class implementing PaymentGateway + one
 * line here.
 */
class PaymentGatewayManager
{
    public const DRIVERS = ['fake', 'cinetpay'];

    public function driver(?string $name = null): PaymentGateway
    {
        $name ??= $this->defaultDriver();

        return match ($name) {
            'fake' => $this->fake(),
            'cinetpay' => app(CinetPayGateway::class),
            default => throw new InvalidArgumentException("Unknown payment driver [{$name}]."),
        };
    }

    public function defaultDriver(): string
    {
        return (string) config('services.payment.driver', 'fake');
    }

    private function fake(): PaymentGateway
    {
        // The simulator would let anyone "pay" for free.
        if (app()->isProduction()) {
            throw new InvalidArgumentException('The fake payment driver is disabled in production.');
        }

        return app(FakePaymentGateway::class);
    }
}
