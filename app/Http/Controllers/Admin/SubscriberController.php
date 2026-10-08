<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Subscribers\DeleteSubscriber;
use App\Actions\Subscribers\SaveSubscriber;
use App\Enums\PassStatus;
use App\Enums\Removal;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guichet\SubscriberRequest;
use App\Models\Payment;
use App\Models\Subscriber;
use App\Models\User;
use App\Models\Zone;
use App\Support\BoardingPoints;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Clients (abonnés) in the back office: list with search and filters, file
 * with the payment history, create, edit, delete (archive once they paid).
 */
class SubscriberController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Subscriber::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'zone' => ['nullable', 'integer'],
            'statut' => ['nullable', 'in:abonne,sans,photo,archive'],
        ]);
        $search = trim($filters['q'] ?? '');
        $status = $filters['statut'] ?? null;
        $today = today()->toDateString();
        $running = fn ($q) => $q->where('status', PassStatus::Active)->whereDate('starts_on', '<=', $today)->whereDate('ends_on', '>=', $today);

        $subscribers = Subscriber::query()
            ->when($status === 'archive', fn (Builder $q) => $q->onlyTrashed())
            ->with(['stop.zone', 'subscriptions' => fn ($q) => $running($q)])
            ->withCount('subscriptions')
            ->when($search !== '', fn (Builder $q) => $this->search($q, $search))
            ->when($filters['zone'] ?? null, fn (Builder $q, $zone) => $q->whereHas('stop', fn (Builder $s) => $s->where('zone_id', $zone)))
            ->when($status === 'abonne', fn (Builder $q) => $q->whereHas('subscriptions', $running))
            ->when($status === 'sans', fn (Builder $q) => $q->whereDoesntHave('subscriptions', $running))
            ->when($status === 'photo', fn (Builder $q) => $q->whereNull('photo_path'))
            ->orderBy('full_name')
            ->paginate(30)
            ->withQueryString();

        return view('admin.subscribers.index', [
            'subscribers' => $subscribers,
            'filters' => ['q' => $search, 'zone' => $filters['zone'] ?? null, 'statut' => $status],
            'zoneOptions' => ['' => 'Toutes les zones'] + Zone::query()->orderBy('line_id')->orderBy('position')->pluck('name', 'id')->all(),
            'total' => Subscriber::query()->count(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Subscriber::class);

        return view('admin.subscribers.form', [
            'subscriber' => null,
            'stopOptions' => BoardingPoints::options(),
        ]);
    }

    public function store(SubscriberRequest $request, SaveSubscriber $save): RedirectResponse
    {
        $actor = $request->user();
        abort_if($actor === null, 403);

        $photo = $request->file('photo');
        $subscriber = $save->handle($actor, null, $request->subscriberData(), $request->boolean('link_account'), $photo instanceof UploadedFile ? $photo : null);

        return redirect()->route('admin.subscribers.show', $subscriber)->with('success', 'Client '.$subscriber->full_name.' enregistré.');
    }

    public function show(Subscriber $subscriber): View
    {
        Gate::authorize('view', $subscriber);

        return view('admin.subscribers.show', [
            'subscriber' => $subscriber->load(['stop.zone.line', 'user']),
            'subscriptions' => $subscriber->subscriptions()->with('payment')->latest('starts_on')->get(),
            'payments' => Payment::query()->where('subscriber_id', $subscriber->id)->with(['seller', 'stop', 'zone'])->latest('id')->get(),
        ]);
    }

    public function edit(Subscriber $subscriber): View
    {
        Gate::authorize('update', $subscriber);

        return view('admin.subscribers.form', [
            'subscriber' => $subscriber,
            'stopOptions' => BoardingPoints::options(),
            'accountForPhone' => $subscriber->phone !== null
                ? User::role(RoleName::Client->value)->where('phone', $subscriber->phone)->first()
                : null,
        ]);
    }

    public function update(SubscriberRequest $request, Subscriber $subscriber, SaveSubscriber $save): RedirectResponse
    {
        $actor = $request->user();
        abort_if($actor === null, 403);

        $photo = $request->file('photo');
        $save->handle($actor, $subscriber, $request->subscriberData(), $request->boolean('link_account'), $photo instanceof UploadedFile ? $photo : null);

        return redirect()->route('admin.subscribers.show', $subscriber)->with('success', 'Client enregistré.');
    }

    public function destroy(Request $request, Subscriber $subscriber, DeleteSubscriber $delete): RedirectResponse
    {
        Gate::authorize('delete', $subscriber);
        $actor = $request->user();
        abort_if($actor === null, 403);

        return $delete->handle($actor, $subscriber) === Removal::Deleted
            ? redirect()->route('admin.subscribers.index')->with('success', 'Client '.$subscriber->full_name.' supprimé.')
            : redirect()->route('admin.subscribers.index')->with('success', 'Client '.$subscriber->full_name.' supprimé de la liste. Ses reçus restent dans l’historique (filtre « Supprimés »).');
    }

    /**
     * Name words, phone (full or partial digits), or receipt number.
     *
     * @param  Builder<Subscriber>  $query
     */
    private function search(Builder $query, string $search): void
    {
        $phone = PhoneNumber::normalize($search);
        $digits = (string) preg_replace('/\D+/', '', $search);

        $query->where(function (Builder $q) use ($search, $phone, $digits) {
            $q->where(function (Builder $names) use ($search) {
                foreach (preg_split('/\s+/', $search) ?: [] as $word) {
                    $names->where('full_name', 'like', '%'.$word.'%');
                }
            });

            if ($phone !== null) {
                $q->orWhere('phone', $phone);
            } elseif (strlen($digits) >= 4) {
                $q->orWhere('phone', 'like', "%{$digits}%");
            }

            $q->orWhereIn('id', Payment::query()->select('subscriber_id')->where('receipt_number', 'like', '%'.$search.'%'));
        });
    }
}
