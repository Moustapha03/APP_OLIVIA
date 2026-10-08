<?php

namespace App\Actions\Subscribers;

use App\Enums\PassStatus;
use App\Enums\Removal;
use App\Models\Payment;
use App\Models\Subscriber;
use App\Models\User;
use App\Services\Photos\SubscriberPhoto;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

/**
 * "Supprimer" a client (subscriber).
 *
 * - refused while a subscription is running or paid for a coming month;
 * - the photo is always erased (law 2013-450: no longer needed);
 * - never paid: really deleted; already paid: archived (soft delete), so
 *   receipts and exports keep the name.
 */
class DeleteSubscriber
{
    public function __construct(private readonly SubscriberPhoto $photos) {}

    /**
     * @throws ValidationException
     */
    public function handle(User $actor, Subscriber $subscriber): Removal
    {
        $running = $subscriber->subscriptions()
            ->where('status', PassStatus::Active)
            ->whereDate('ends_on', '>=', today()->toDateString())
            ->orderByDesc('ends_on')
            ->first();

        if ($running !== null) {
            throw ValidationException::withMessages([
                'subscriber' => $subscriber->full_name.' a un abonnement valable jusqu’au '.$running->ends_on->translatedFormat('j F Y').' : impossible de le supprimer avant cette date.',
            ]);
        }

        return DB::transaction(function () use ($actor, $subscriber) {
            $this->photos->delete($subscriber);

            $hasHistory = $subscriber->subscriptions()->exists()
                || Payment::query()->where('subscriber_id', $subscriber->id)->exists();

            // Archived: the name stays on receipts (accounts). Deleted: no trace of
            // the person anywhere, including the earlier activity entries.
            activity('subscribers')->causedBy($actor)->performedOn($subscriber)->event('subscriber.deleted')
                ->withProperties($hasHistory ? ['full_name' => $subscriber->full_name, 'archived' => true] : ['archived' => false])
                ->log($hasHistory ? 'Abonné archivé' : 'Abonné supprimé');

            if ($hasHistory) {
                $subscriber->delete();

                return Removal::Archived;
            }

            $subscriber->forceDelete();

            // After the delete: its own "deleted" log entry is anonymised too.
            Activity::query()->whereMorphedTo('subject', $subscriber)->update(['properties' => json_encode(['anonymised' => true])]);

            return Removal::Deleted;
        });
    }
}
