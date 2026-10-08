<?php

namespace App\Policies;

use App\Models\Subscriber;
use App\Models\User;

/**
 * Subscribers (abonnés): managed by the agency (customers.*) and by the
 * customer account they are attached to. Controllers see a photo only
 * through the signed link of the pass they just scanned (viewPhoto).
 */
class SubscriberPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('customers.view') || $actor->can('subscriptions.view-all');
    }

    public function view(User $actor, Subscriber $subscriber): bool
    {
        return $this->owns($actor, $subscriber)
            || $actor->can('customers.view')
            || $actor->can('subscriptions.view-all');
    }

    public function create(User $actor): bool
    {
        return $actor->can('customers.create') || $actor->can('passes.purchase');
    }

    public function update(User $actor, Subscriber $subscriber): bool
    {
        return $this->owns($actor, $subscriber) || $actor->can('customers.create');
    }

    /**
     * Agency only (a customer account cannot delete an abonné with history).
     */
    public function delete(User $actor, Subscriber $subscriber): bool
    {
        return $actor->can('customers.delete');
    }

    /**
     * Least privilege: the owner account and the people who identify a
     * subscriber at the desk (customers.view). Controllers see the photo of
     * the pass they just scanned only, through a short-lived signed link
     * (VerifyPass::photoUrl), so photos cannot be fetched by iterating ids.
     */
    public function viewPhoto(User $actor, Subscriber $subscriber): bool
    {
        return $this->owns($actor, $subscriber)
            || $actor->can('customers.view');
    }

    /**
     * Buy online for this subscriber.
     */
    public function purchase(User $actor, Subscriber $subscriber): bool
    {
        return $this->owns($actor, $subscriber) && $actor->can('passes.purchase');
    }

    private function owns(User $actor, Subscriber $subscriber): bool
    {
        return $subscriber->user_id !== null && $subscriber->user_id === $actor->id;
    }
}
