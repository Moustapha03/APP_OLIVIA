<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

/**
 * A customer sees only their own payments, an agent only the sales they
 * made (IDOR); accountants need payments.view.
 */
class PaymentPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('payments.view');
    }

    public function view(User $actor, Payment $payment): bool
    {
        return ($payment->user_id !== null && $payment->user_id === $actor->id)
            || ($payment->sold_by !== null && $payment->sold_by === $actor->id)
            || $actor->can('payments.view');
    }
}
