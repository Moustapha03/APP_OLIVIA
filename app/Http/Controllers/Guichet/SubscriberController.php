<?php

namespace App\Http\Controllers\Guichet;

use App\Actions\Subscribers\SaveSubscriber;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guichet\SubscriberRequest;
use App\Models\Subscriber;
use App\Models\User;
use App\Support\BoardingPoints;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Subscribers (abonnés) at the agency: search by name or phone, create,
 * edit, take the photo.
 */
class SubscriberController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Subscriber::class);

        $search = $request->string('q')->trim()->toString();

        return view('guichet.subscribers.index', [
            'search' => $search,
            'subscribers' => $search === '' ? collect() : self::search($search),
            'stopOptions' => BoardingPoints::options(),
            'prefillPhone' => PhoneNumber::normalize($search) !== null ? PhoneNumber::format(PhoneNumber::normalize($search)) : null,
        ]);
    }

    public function store(SubscriberRequest $request, SaveSubscriber $save): RedirectResponse
    {
        $agent = $request->user();
        abort_if($agent === null, 403);

        $photo = $request->file('photo');
        $subscriber = $save->handle($agent, null, $request->subscriberData(), $request->boolean('link_account'), $photo instanceof UploadedFile ? $photo : null);

        $target = $request->input('then') === 'subscription'
            ? route('guichet.subscriptions.create', ['abonne' => $subscriber->id])
            : route('guichet.subscribers.index', ['q' => $subscriber->full_name]);

        return redirect($target)->with('success', 'Abonné '.$subscriber->full_name.' enregistré.');
    }

    public function edit(Subscriber $subscriber): View
    {
        Gate::authorize('update', $subscriber);

        return view('guichet.subscribers.edit', [
            'subscriber' => $subscriber->load(['stop.zone', 'user', 'subscriptions' => fn ($q) => $q->latest('starts_on')->limit(12)]),
            'stopOptions' => BoardingPoints::options(),
            'accountForPhone' => $subscriber->phone !== null
                ? User::role(RoleName::Client->value)->where('phone', $subscriber->phone)->first()
                : null,
        ]);
    }

    public function update(SubscriberRequest $request, Subscriber $subscriber, SaveSubscriber $save): RedirectResponse
    {
        $agent = $request->user();
        abort_if($agent === null, 403);

        $photo = $request->file('photo');
        $save->handle($agent, $subscriber, $request->subscriberData(), $request->boolean('link_account'), $photo instanceof UploadedFile ? $photo : null);

        return redirect()->route('guichet.subscribers.edit', $subscriber)->with('success', 'Abonné enregistré.');
    }

    /**
     * Name contains the words typed, or phone matches (full or partial).
     *
     * @return Collection<int, Subscriber>
     */
    public static function search(string $search): Collection
    {
        $phone = PhoneNumber::normalize($search);
        $digits = (string) preg_replace('/\D+/', '', $search);
        $hasName = preg_match('/\pL/u', $search) === 1;

        // Nothing meaningful typed ("12"): no result rather than everybody.
        if ($phone === null && strlen($digits) < 4 && ! $hasName) {
            return collect();
        }

        return Subscriber::query()
            ->with(['stop.zone', 'subscriptions' => fn ($q) => $q->running()->orderBy('starts_on')])
            ->where(function (Builder $q) use ($search, $phone, $digits, $hasName) {
                if ($phone !== null) {
                    $q->orWhere('phone', $phone);
                } elseif (strlen($digits) >= 4) {
                    $q->orWhere('phone', 'like', "%{$digits}%");
                }

                if ($hasName) {
                    $q->orWhere(function (Builder $names) use ($search) {
                        foreach (preg_split('/\s+/', $search) ?: [] as $word) {
                            $names->where('full_name', 'like', '%'.$word.'%');
                        }
                    });
                }
            })
            ->orderBy('full_name')
            ->limit(25)
            ->get();
    }
}
