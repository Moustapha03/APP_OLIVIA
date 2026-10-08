<?php

namespace App\Http\Controllers\Guichet;

use App\Actions\Cash\CashSessionTotals;
use App\Actions\Sales\ApproveDiscount;
use App\Actions\Sales\SellAtCounter;
use App\Enums\FareType;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guichet\CounterSubscriptionRequest;
use App\Models\Subscriber;
use App\Services\Photos\SubscriberPhoto;
use App\Support\BoardingPoints;
use App\Support\Money;
use App\Support\SubscriptionMonths;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Monthly subscriptions at the agency, the main activity: find the
 * subscriber, choose formula and month (price of the boarding point's
 * zone), take the payment. Reached only with an open cash register.
 */
class SubscriptionSaleController extends Controller
{
    public function create(Request $request): View
    {
        Gate::authorize('tickets.sell');

        $agent = $request->user();
        abort_if($agent === null, 403);

        $cashSession = $agent->openCashSession()->firstOrFail();
        $search = $request->string('q')->trim()->toString();

        $subscriber = $request->filled('abonne')
            ? Subscriber::query()
                ->with(['stop.zone', 'subscriptions' => fn ($q) => $q->running()->orderBy('starts_on')])
                ->findOrFail($request->integer('abonne'))
            : null;

        $zone = $subscriber?->stop?->zone;

        // Price of each formula in the subscriber's zone (null = not sold there).
        $prices = [];
        foreach (FareType::subscriptions() as $type) {
            $prices[$type->value] = $zone?->fareOn($type)?->amount;
        }

        return view('guichet.subscriptions.create', [
            'cashSession' => $cashSession,
            'totals' => CashSessionTotals::for($cashSession),
            'recentSales' => $cashSession->payments()->with(['subscriber', 'stop', 'zone'])->latest('id')->limit(8)->get(),
            'search' => $search,
            'results' => $subscriber === null && $search !== '' ? SubscriberController::search($search) : collect(),
            'subscriber' => $subscriber,
            'zone' => $zone,
            'prices' => $prices,
            'months' => SubscriptionMonths::onSale(),
            'methods' => PaymentMethod::atCounter(),
            'stopOptions' => BoardingPoints::options(),
        ]);
    }

    public function store(CounterSubscriptionRequest $request, SellAtCounter $sell, SubscriberPhoto $photos, ApproveDiscount $approve): RedirectResponse
    {
        $agent = $request->user();
        abort_if($agent === null, 403);

        $cashSession = $agent->openCashSession()->firstOrFail();
        Gate::authorize('view', $cashSession);

        $subscriber = $request->subscriber();

        // Photo taken at the desk.
        $photo = $request->file('photo');
        if ($photo instanceof UploadedFile) {
            $photos->store($subscriber, $photo);
            $subscriber->refresh();
        }

        abort_if($subscriber->stop === null, 422, 'Abonné sans point de montée.');

        $payment = $sell->handle(
            agent: $agent,
            session: $cashSession,
            zone: $subscriber->stop->zone,
            stop: $subscriber->stop,
            type: $request->formula(),
            method: $request->paymentMethod(),
            subscriber: $subscriber,
            month: $request->month(),
            externalReference: $request->validated('external_reference'),
            agreedAmount: $request->agreedAmount(),
            discountReason: $request->validated('discount_reason'),
            approver: $request->discountApprover($approve),
        );

        if ($request->boolean('print')) {
            return redirect()->route('guichet.sales.receipt', ['payment' => $payment->reference, 'imprimer' => 1]);
        }

        return redirect()->route('guichet.subscriptions.create')
            ->with('success', 'Reçu '.$payment->receipt_number.' : '.$subscriber->full_name.', '.$payment->purpose->shortLabel().' '.SubscriptionMonths::label($payment->period_month ?? today()).' — '.Money::format($payment->amount).'.')
            ->with('last_sale', $payment->reference);
    }
}
