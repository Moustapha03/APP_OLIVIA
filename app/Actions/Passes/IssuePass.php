<?php

namespace App\Actions\Passes;

use App\Enums\PassChannel;
use App\Enums\PassStatus;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Ticket;
use Illuminate\Support\Str;
use LogicException;

/**
 * Creates the pass of a confirmed payment. Must run inside the
 * transaction that marked the payment as succeeded (ConfirmPayment,
 * SellAtCounter). The price paid is copied from the payment: later fare
 * changes never affect it (rule 6).
 */
class IssuePass
{
    public function handle(Payment $payment, PassChannel $channel = PassChannel::Online, ?int $soldBy = null): Ticket|Subscription
    {
        if (! $payment->isSucceeded()) {
            throw new LogicException("Payment {$payment->reference} is not paid: no pass.");
        }

        $pass = $payment->purpose->isSubscription()
            ? $this->subscription($payment, $channel, $soldBy)
            : $this->ticket($payment, $channel, $soldBy);

        $payment->payable()->associate($pass);
        $payment->save();

        return $pass;
    }

    private function ticket(Payment $payment, PassChannel $channel, ?int $soldBy): Ticket
    {
        $ticket = new Ticket;
        $ticket->forceFill([
            'uuid' => (string) Str::uuid(),
            'user_id' => $payment->user_id,
            'zone_id' => $payment->zone_id,
            'stop_id' => $payment->stop_id,
            'fare_id' => $payment->fare_id,
            'payment_id' => $payment->id,
            'price_paid' => $payment->amount,
            // Valid on the day it is issued (paid), until 23:59 (rule 2).
            'valid_on' => today(),
            'rides_allowed' => (int) config('transport.tickets.daily_rides', 2),
            'rides_used' => 0,
            'channel' => $channel,
            'status' => PassStatus::Active,
            'sold_by' => $soldBy,
        ])->save();

        return $ticket;
    }

    private function subscription(Payment $payment, PassChannel $channel, ?int $soldBy): Subscription
    {
        if ($payment->subscriber_id === null || $payment->period_month === null) {
            throw new LogicException("Payment {$payment->reference} has no subscriber or month.");
        }

        $month = $payment->period_month->copy()->startOfMonth();

        $subscription = new Subscription;
        $subscription->forceFill([
            'uuid' => (string) Str::uuid(),
            'subscriber_id' => $payment->subscriber_id,
            'user_id' => $payment->user_id,
            'zone_id' => $payment->zone_id,
            'stop_id' => $payment->stop_id,
            'fare_id' => $payment->fare_id,
            'payment_id' => $payment->id,
            'formula' => $payment->purpose,
            'price_paid' => $payment->amount,
            'starts_on' => $month,
            'ends_on' => $month->copy()->endOfMonth()->startOfDay(),
            'channel' => $channel,
            'status' => PassStatus::Active,
            'sold_by' => $soldBy,
        ])->save();

        return $subscription;
    }
}
