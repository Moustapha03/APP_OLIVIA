<?php

namespace App\Actions\Passes;

use App\Enums\PassStatus;
use App\Models\Subscriber;
use App\Models\Subscription;
use App\Support\SubscriptionMonths;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Checks shared by the online purchase and the desk sale of a
 * subscription: nominative with a photo (rule 3), a month on sale, and
 * never two subscriptions for the same person and month.
 */
class EnsureSubscriptionCanBeSold
{
    /**
     * @throws ValidationException
     */
    public function handle(?Subscriber $subscriber, ?Carbon $month): void
    {
        if ($subscriber === null) {
            throw ValidationException::withMessages(['subscriber_id' => 'Choisissez l’abonné : l’abonnement est personnel.']);
        }

        if (! $subscriber->hasPhoto()) {
            throw ValidationException::withMessages(['photo' => 'Ajoutez d’abord la photo de l’abonné : elle est montrée au contrôle.']);
        }

        if ($month === null || ! SubscriptionMonths::isOnSale($month)) {
            throw ValidationException::withMessages(['month' => 'Choisissez le mois en cours ou le mois prochain.']);
        }

        $exists = Subscription::query()
            ->where('subscriber_id', $subscriber->id)
            ->where('status', PassStatus::Active)
            ->forMonth($month)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'month' => $subscriber->full_name.' a déjà un abonnement pour '.SubscriptionMonths::label($month).'.',
            ]);
        }
    }
}
