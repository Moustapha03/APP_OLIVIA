<?php

namespace App\Services\Payments;

use App\Enums\GatewayStatus;

/**
 * Transaction status as returned by the aggregator API, normalised.
 */
final readonly class GatewayResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public GatewayStatus $status,
        public ?int $amount = null,
        public ?string $currency = null,
        public ?string $transactionId = null,
        public ?string $paymentMethod = null,
        public ?string $message = null,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function waiting(string $message, array $raw = []): self
    {
        return new self(GatewayStatus::Waiting, message: $message, raw: $raw);
    }
}
