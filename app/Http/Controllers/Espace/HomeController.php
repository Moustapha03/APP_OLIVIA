<?php

namespace App\Http\Controllers\Espace;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Customer home: what can be used today, first.
 */
class HomeController extends Controller
{
    public function __invoke(Request $request): View
    {
        Gate::authorize('space.espace');

        $user = $request->user();
        abort_if($user === null, 403);

        $subscriptions = PassController::runningSubscriptionsOf($user);
        $tickets = $user->tickets()->with('zone')->validOn(today())->get()->filter(fn (Ticket $t) => $t->isUsable());
        $reminderDays = (int) config('transport.subscriptions.reminder_days_before', 3);

        return view('espace.home', [
            'passes' => $subscriptions->concat($tickets),
            // Ending soon, and next month not bought yet for that subscriber.
            'expiring' => $subscriptions->first(fn (Subscription $s) => $s->expiresSoon($reminderDays)
                && ! $subscriptions->contains(fn (Subscription $other) => $other->subscriber_id === $s->subscriber_id && $other->starts_on->gt($s->ends_on))),
        ]);
    }
}
