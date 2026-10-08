<?php

namespace App\Services\Qr;

use App\Models\Subscription;
use App\Models\Ticket;

/**
 * A QR code whose signature is valid. It still has to be checked against
 * the database (expired, used up, cancelled…) by the scan (step 7).
 */
final readonly class SignedPass
{
    public function __construct(
        public string $kind,
        public string $uuid,
        public string $keyId,
    ) {}

    public function find(): Ticket|Subscription|null
    {
        return $this->kind === 't'
            ? Ticket::query()->where('uuid', $this->uuid)->first()
            : Subscription::query()->where('uuid', $this->uuid)->first();
    }
}
