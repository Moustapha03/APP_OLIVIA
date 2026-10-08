<?php

namespace App\Contracts;

/**
 * A transport pass: daily ticket or monthly subscription.
 * Its QR code carries only the pass kind and opaque uuid, signed.
 */
interface Pass
{
    /** "t" (ticket) or "s" (subscription), as written in the QR code. */
    public function passKind(): string;

    public function passUuid(): string;
}
