<?php

namespace App\Http\Controllers\Espace;

use App\Actions\Payments\StartOnlinePurchase;
use App\Enums\FareType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Espace\PurchaseRequest;
use App\Models\Line;
use App\Models\Stop;
use App\Support\SubscriptionMonths;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Online purchase in 3 steps: Point de montée → Formule → Paiement.
 */
class PurchaseController extends Controller
{
    /**
     * Step 1: boarding point (grouped by zone, with the zone prices).
     */
    public function create(): View
    {
        Gate::authorize('passes.purchase');

        $today = today();

        $lines = Line::query()->active()
            ->with(['zones' => fn ($q) => $q->active()->with([
                'stops' => fn ($s) => $s->active(),
                'fares' => fn ($f) => $f->inForceOn($today),
            ])])
            ->orderBy('name')
            ->get();

        return view('espace.purchase.stop', ['lines' => $lines]);
    }

    /**
     * Step 2: formula (+ subscriber and month for a subscription).
     */
    public function formula(Request $request, Stop $stop): View|RedirectResponse
    {
        Gate::authorize('passes.purchase');

        if (! $stop->isSellable()) {
            return redirect()->route('espace.purchase.create')->with('warning', 'Ce point de montée n’est plus desservi.');
        }

        $user = $request->user();
        abort_if($user === null, 403);

        $prices = [];
        foreach (FareType::cases() as $type) {
            $prices[$type->value] = $stop->zone->fareOn($type)?->amount;
        }

        return view('espace.purchase.formula', [
            'stop' => $stop,
            'prices' => array_filter($prices, fn (?int $amount) => $amount !== null),
            'selected' => FareType::tryFrom((string) $request->query('formule')),
            'subscribers' => $user->subscribers()->orderBy('full_name')->get(),
            'months' => SubscriptionMonths::onSale(),
            'dailyRides' => (int) config('transport.tickets.daily_rides'),
        ]);
    }

    /**
     * Step 3: freeze the order and go to the payment page.
     */
    public function store(PurchaseRequest $request, StartOnlinePurchase $start): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        [, $checkoutUrl] = $start->handle($user, $request->stop(), $request->fareType(), $request->subscriber(), $request->month());

        return redirect()->away($checkoutUrl);
    }
}
