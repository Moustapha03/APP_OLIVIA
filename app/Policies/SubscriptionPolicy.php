<?php

namespace App\Policies;

use App\Models\Subscription;
use App\Models\User;

/**
 * A customer sees and renews the subscriptions of the subscribers attached
 * to their account (IDOR); staff need subscriptions.view-all.
 */
class SubscriptionPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('subscriptions.view-all');
    }

    public function view(User $actor, Subscription $subscription): bool
    {
        return $this->owns($actor, $subscription) || $actor->can('subscriptions.view-all');
    }

    public function renew(User $actor, Subscription $subscription): bool
    {
        return $this->owns($actor, $subscription) && $actor->can('passes.purchase');
    }

    private function owns(User $actor, Subscription $subscription): bool
    {
        $subscription->loadMissing('subscriber');

        return ($subscription->subscriber->user_id !== null && $subscription->subscriber->user_id === $actor->id)
            || ($subscription->user_id !== null && $subscription->user_id === $actor->id);
    }
}
