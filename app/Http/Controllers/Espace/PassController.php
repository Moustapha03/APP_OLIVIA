<?php

namespace App\Http\Controllers\Espace;

use App\Enums\PassStatus;
use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Qr\PassSigner;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * "Mes titres": the customer's daily tickets and the subscriptions of the
 * subscribers attached to their account. QR code in large, downloadable.
 * Passes are reached by uuid and checked with their policy (IDOR).
 */
class PassController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('space.espace');

        $user = $request->user();
        abort_if($user === null, 403);

        $subscriptions = self::subscriptionsOf($user)->get();
        $tickets = $user->tickets()->with('zone')->orderByDesc('valid_on')->orderByDesc('id')->limit(30)->get();

        return view('espace.passes.index', [
            'currentSubscriptions' => $subscriptions->filter(fn (Subscription $s) => ! $s->isExpired() && $s->status === PassStatus::Active),
            'todayTickets' => $tickets->filter(fn (Ticket $t) => $t->isUsable()),
            'history' => $subscriptions->filter(fn (Subscription $s) => $s->isExpired() || $s->status !== PassStatus::Active)
                ->concat($tickets->reject(fn (Ticket $t) => $t->isUsable()))
                ->sortByDesc('created_at')
                ->values(),
            'hasSubscribers' => $user->subscribers()->exists(),
        ]);
    }

    public function ticket(Ticket $ticket, PassSigner $signer): View
    {
        Gate::authorize('view', $ticket);

        return view('espace.passes.ticket', [
            'ticket' => $ticket->load('zone.line'),
            'qr' => $signer->sign($ticket),
        ]);
    }

    public function subscription(Subscription $subscription, PassSigner $signer): View
    {
        Gate::authorize('view', $subscription);

        return view('espace.passes.subscription', [
            'subscription' => $subscription->load(['zone.line', 'stop', 'subscriber']),
            'qr' => $signer->sign($subscription),
        ]);
    }

    public function ticketPdf(Ticket $ticket, PassSigner $signer): Response
    {
        Gate::authorize('view', $ticket);

        return $this->pdf($ticket->load('zone.line'), $signer, 'ticket-'.$ticket->valid_on->format('Y-m-d'));
    }

    public function subscriptionPdf(Subscription $subscription, PassSigner $signer): Response
    {
        Gate::authorize('view', $subscription);

        return $this->pdf($subscription->load(['zone.line', 'stop', 'subscriber']), $signer, 'abonnement-'.$subscription->starts_on->format('Y-m'));
    }

    /**
     * Subscriptions of the subscribers attached to the account, or bought by it.
     *
     * @return Builder<Subscription>
     */
    public static function subscriptionsOf(User $user): Builder
    {
        return Subscription::query()
            ->with(['zone', 'stop', 'subscriber'])
            ->where(fn (Builder $q) => $q->where('user_id', $user->id)->orWhereHas('subscriber', fn (Builder $s) => $s->where('user_id', $user->id)))
            ->orderByDesc('starts_on');
    }

    /**
     * @return Collection<int, Subscription>
     */
    public static function runningSubscriptionsOf(User $user): Collection
    {
        return self::subscriptionsOf($user)->running()->get()->sortBy('starts_on')->values();
    }

    private function pdf(Ticket|Subscription $pass, PassSigner $signer, string $name): Response
    {
        return Pdf::loadView('espace.passes.pdf', [
            'pass' => $pass,
            'isSubscription' => $pass instanceof Subscription,
            'qr' => $signer->sign($pass),
        ])->setPaper('a5')->download($name.'.pdf');
    }
}
