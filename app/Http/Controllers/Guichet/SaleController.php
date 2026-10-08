<?php

namespace App\Http\Controllers\Guichet;

use App\Actions\Cash\CashSessionTotals;
use App\Actions\Sales\ApproveDiscount;
use App\Actions\Sales\CancelCounterSale;
use App\Actions\Sales\SellAtCounter;
use App\Enums\FareType;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guichet\CancelSaleRequest;
use App\Http\Requests\Guichet\CounterSaleRequest;
use App\Models\Line;
use App\Models\Payment;
use App\Services\Qr\PassSigner;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Daily tickets at the desk (one click per zone), receipts and supervised
 * cancellation. Reached only with an open cash register ("cash-session").
 */
class SaleController extends Controller
{
    public function create(Request $request): View
    {
        Gate::authorize('tickets.sell');

        $agent = $request->user();
        abort_if($agent === null, 403);

        $cashSession = $agent->openCashSession()->firstOrFail();
        $today = today();

        return view('guichet.sales.create', [
            'cashSession' => $cashSession,
            'totals' => CashSessionTotals::for($cashSession),
            'lines' => Line::query()->active()
                ->with(['zones' => fn ($zones) => $zones->active()->with([
                    'stops' => fn ($stops) => $stops->active(),
                    'fares' => fn ($fares) => $fares->ofType(FareType::Daily)->inForceOn($today),
                ])])
                ->orderBy('name')
                ->get(),
            'recentSales' => $cashSession->payments()->with(['subscriber', 'stop', 'zone'])->latest('id')->limit(8)->get(),
            'methods' => PaymentMethod::atCounter(),
        ]);
    }

    public function store(CounterSaleRequest $request, SellAtCounter $sell, ApproveDiscount $approve): RedirectResponse
    {
        $agent = $request->user();
        abort_if($agent === null, 403);

        $cashSession = $agent->openCashSession()->firstOrFail();
        Gate::authorize('view', $cashSession);

        $zone = $request->zone();

        $payment = $sell->handle(
            agent: $agent,
            session: $cashSession,
            zone: $zone,
            stop: null,
            type: FareType::Daily,
            method: $request->paymentMethod(),
            externalReference: $request->validated('external_reference'),
            agreedAmount: $request->agreedAmount(),
            discountReason: $request->validated('discount_reason'),
            approver: $request->discountApprover($approve),
        );

        if ($request->boolean('print')) {
            return redirect()->route('guichet.sales.receipt', ['payment' => $payment->reference, 'imprimer' => 1]);
        }

        return redirect()->route('guichet.sales.create')
            ->with('success', 'Reçu '.$payment->receipt_number.' : ticket du jour '.$zone->name.' — '.Money::format($payment->amount).'.')
            ->with('last_sale', $payment->reference);
    }

    /**
     * Thermal printer receipt (58 or 80 mm), with the pass QR code.
     * Reprint also works after the register is closed.
     */
    public function receipt(Request $request, Payment $payment, PassSigner $signer): View
    {
        Gate::authorize('view', $payment);
        abort_unless($payment->gateway === 'counter' && $payment->payable !== null, 404);

        $width = in_array($request->integer('largeur'), [58, 80], true)
            ? $request->integer('largeur')
            : (int) config('transport.counter.receipt_width', 80);

        return view('guichet.sales.receipt', [
            'payment' => $payment->load(['zone.line', 'stop', 'seller', 'subscriber', 'user']),
            'pass' => $payment->payable,
            'qr' => $payment->isSucceeded() ? $signer->sign($payment->payable) : null,
            'width' => $width,
            'autoPrint' => $request->boolean('imprimer'),
        ]);
    }

    public function cancel(CancelSaleRequest $request, Payment $payment, CancelCounterSale $cancel): RedirectResponse
    {
        $agent = $request->user();
        abort_if($agent === null, 403);
        Gate::authorize('view', $payment);

        try {
            $cancel->handle(
                $agent,
                $payment,
                $request->string('supervisor_login')->toString(),
                $request->string('supervisor_password')->toString(),
                $request->string('reason')->trim()->toString(),
            );
        } catch (ValidationException $e) {
            // Shown inside the cancellation modal of this very sale.
            throw $e->errorBag($request->errorBagName());
        }

        return back()->with('success', 'Vente annulée. Rendez '.Money::format($payment->amount).' au client.');
    }
}
