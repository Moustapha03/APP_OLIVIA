<?php

namespace App\Http\Controllers\Espace;

use App\Enums\PassStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Espace\SubscriberRequest;
use App\Models\Subscriber;
use App\Services\Photos\SubscriberPhoto;
use App\Support\BoardingPoints;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * "Mes abonnés": the people the customer buys subscriptions for
 * (themselves, a relative). Always attached to the customer's account.
 */
class SubscriberController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('space.espace');

        $user = $request->user();
        abort_if($user === null, 403);

        return view('espace.subscribers.index', [
            'subscribers' => $user->subscribers()->with(['stop.zone', 'subscriptions' => fn ($q) => $q->running()->orderBy('starts_on')])->orderBy('full_name')->get(),
            'stopOptions' => BoardingPoints::options(),
            // Only an internal page of the purchase flow (never an external link).
            'back' => is_string($back = $request->query('retour')) && str_starts_with($back, '/espace/acheter/') ? $back : null,
        ]);
    }

    public function store(SubscriberRequest $request, SubscriberPhoto $photos): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $subscriber = new Subscriber($request->subscriberData());
        $subscriber->user_id = $user->id;
        $subscriber->save();

        /** @var UploadedFile $photo */
        $photo = $request->file('photo');
        $photos->store($subscriber, $photo);

        $back = $request->input('back');

        return (is_string($back) && str_starts_with($back, '/espace/acheter/') ? redirect($back) : redirect()->route('espace.subscribers.index'))
            ->with('success', $subscriber->full_name.' est enregistré. Vous pouvez acheter son abonnement.');
    }

    public function update(SubscriberRequest $request, Subscriber $subscriber, SubscriberPhoto $photos): RedirectResponse
    {
        $data = $request->subscriberData();
        $photo = $request->file('photo');

        // The pass is personal: while one is valid (or paid for a coming month),
        // the holder's name and face can only be changed at the agency, with ID.
        $locked = $subscriber->subscriptions()
            ->where('status', PassStatus::Active)
            ->whereDate('ends_on', '>=', today()->toDateString())
            ->exists();

        if ($locked && ($data['full_name'] !== $subscriber->full_name || $photo instanceof UploadedFile)) {
            return redirect()->route('espace.subscribers.index')->with('error', 'Un abonnement est en cours : le nom et la photo de '.$subscriber->full_name.' se changent uniquement à l’agence, avec une pièce d’identité.');
        }

        $subscriber->update($data);

        if ($photo instanceof UploadedFile) {
            $photos->store($subscriber, $photo);
            activity('subscribers')->causedBy($request->user())->performedOn($subscriber)->event('subscriber.photo')->log('Photo changée en ligne');
        }

        return redirect()->route('espace.subscribers.index')->with('success', 'Abonné enregistré.');
    }
}
