<?php

namespace App\Jobs;

use App\Actions\Payments\ConfirmPayment;
use App\Models\Payment;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Queued after a signed notification: the webhook answers at once, the
 * status is verified with the aggregator here. Unique per payment, and
 * ConfirmPayment is idempotent anyway: a replayed webhook is harmless.
 */
class ProcessPaymentNotification implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 6;

    /** @var list<int> seconds between attempts */
    public array $backoff = [10, 30, 60, 120, 300];

    public function __construct(public readonly int $paymentId) {}

    public function uniqueId(): string
    {
        return (string) $this->paymentId;
    }

    public function handle(ConfirmPayment $confirm): void
    {
        $payment = Payment::query()->find($this->paymentId);

        if ($payment === null) {
            return;
        }

        $payment = $confirm->handle($payment);

        // Notified, but the aggregator still says "waiting": look again later.
        if ($payment->isPending() && $this->attempts() < $this->tries) {
            $this->release($this->backoff[$this->attempts() - 1] ?? 300);
        }
    }
}
