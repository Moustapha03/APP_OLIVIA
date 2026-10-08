<?php

namespace App\Actions\Subscribers;

use App\Enums\RoleName;
use App\Models\Subscriber;
use App\Models\User;
use App\Services\Photos\SubscriberPhoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates a subscriber at the agency, with their photo.
 *
 * Link to a customer account: only when the agent ticks it, and only to
 * the "client" account having the same phone number. Then that account
 * sees this subscriber's passes online (e.g. the parent who pays). Never
 * automatic: a self-registered account does not prove phone ownership.
 */
class SaveSubscriber
{
    public function __construct(private readonly SubscriberPhoto $photos) {}

    /**
     * @param  array{full_name: string, phone: string|null, stop_id: int, notes: string|null}  $data
     */
    public function handle(User $agent, ?Subscriber $subscriber, array $data, bool $linkAccount, ?UploadedFile $photo): Subscriber
    {
        $subscriber = DB::transaction(function () use ($agent, $subscriber, $data, $linkAccount) {
            $subscriber ??= new Subscriber;
            $subscriber->fill($data);

            $account = $linkAccount && $data['phone'] !== null
                ? User::role(RoleName::Client->value)->where('phone', $data['phone'])->first()
                : null;

            $subscriber->user_id = $account?->id;
            $subscriber->save();

            activity('subscribers')->causedBy($agent)->performedOn($subscriber)
                ->event($subscriber->wasRecentlyCreated ? 'subscriber.created' : 'subscriber.updated')
                ->withProperties(['linked_account' => $account?->id])
                ->log($subscriber->wasRecentlyCreated ? 'Abonné créé à l’agence' : 'Abonné modifié à l’agence');

            return $subscriber;
        });

        if ($photo !== null) {
            $this->photos->store($subscriber, $photo);
        }

        return $subscriber;
    }
}
