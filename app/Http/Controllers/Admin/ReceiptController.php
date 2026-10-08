<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FareType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Zone;
use App\Services\Qr\PassSigner;
use App\Support\PhoneNumber;
use App\Support\SubscriptionMonths;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Payments and receipts: history of every transaction (filters, search,
 * detail page with the QR code, reprint), and the CSV export of paid receipts in
 * the agency's own format (N° ; Date ; Abonné ; Téléphone ; Point de montée ;
 * Mois ; Formule ; Paiement ; Réf. ; Montant FCFA + TOTAL line).
 */
class ReceiptController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('subscriptions.view-all');

        $filters = $this->filters($request);
        $receipts = $this->query($filters)->with(['subscriber', 'stop', 'zone', 'seller'])->paginate(50)->withQueryString();

        $totals = $this->query($filters)
            ->reorder()
            ->selectRaw('payment_method, COUNT(*) as n, COALESCE(SUM(amount), 0) as total')
            ->groupBy('payment_method')
            ->get();

        return view('admin.receipts.index', [
            'receipts' => $receipts,
            'filters' => $filters,
            'totals' => $totals,
            'grandTotal' => (int) $totals->sum('total'),
            'count' => (int) $totals->sum('n'),
            'months' => $this->monthOptions(),
            'methods' => [...PaymentMethod::atCounter(), PaymentMethod::Online],
            'zones' => Zone::query()->with('line')->orderBy('line_id')->orderBy('position')->get(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('subscriptions.view-all');

        // The agency format has no status column: paid receipts only.
        $filters = [...$this->filters($request), 'status' => 'paid'];
        $name = 'recus-'.($filters['month'] ?? 'tous').'.csv';

        activity('audit')->causedBy($request->user())->event('receipts.exported')
            ->withProperties(['filters' => $filters])
            ->log('Export CSV des reçus');

        return response()->streamDownload(function () use ($filters) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\u{FEFF}"); // UTF-8 BOM: accents shown correctly in Excel

            // A cell starting with = + - @ would run as a formula in Excel (names and
            // references come from clients): neutralised with a leading apostrophe.
            $cell = fn (mixed $c) => preg_match('/^[=+\-@\t\r]/', (string) $c) === 1 ? "'".$c : (string) $c;
            $line = fn (array $cells) => fwrite($out, implode(';', array_map(fn ($c) => '"'.str_replace('"', '""', $cell($c)).'"', $cells))."\r\n");

            $line(['N°', 'Date', 'Abonné', 'Téléphone', 'Point de montée', 'Mois', 'Formule', 'Paiement', 'Réf.', 'Montant FCFA']);

            $total = 0;
            $this->query($filters)->with(['subscriber', 'stop', 'zone'])->chunk(500, function ($payments) use ($line, &$total) {
                foreach ($payments as $payment) {
                    /** @var Payment $payment */
                    $total += $payment->amount;
                    $line([
                        $payment->receipt_number,
                        ($payment->confirmed_at ?? $payment->created_at)->format('d/m/Y'),
                        $payment->subscriber->full_name ?? 'Ticket du jour',
                        PhoneNumber::format($payment->subscriber?->phone),
                        $payment->stop->name ?? $payment->zone->name,
                        $payment->period_month ? SubscriptionMonths::label($payment->period_month) : ($payment->confirmed_at ?? $payment->created_at)->format('d/m/Y'),
                        $payment->purpose->shortLabel(),
                        $payment->payment_method?->label(),
                        $payment->external_reference,
                        $payment->amount,
                    ]);
                }
            });

            $line(['', '', '', '', '', '', '', '', 'TOTAL', $total]);
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * One transaction: who, what, how, its pass and QR code, its history.
     */
    public function show(Payment $payment, PassSigner $signer): View
    {
        Gate::authorize('subscriptions.view-all');

        $payment->load(['subscriber', 'zone.line', 'stop', 'seller', 'canceller', 'cashSession.user', 'payable', 'user']);
        $pass = $payment->payable;

        return view('admin.receipts.show', [
            'payment' => $payment,
            'pass' => $pass,
            'qr' => $payment->isSucceeded() && $pass !== null ? $signer->sign($pass) : null,
            'history' => Activity::query()
                ->where(fn (Builder $q) => $q->whereMorphedTo('subject', $payment)
                    ->when($pass, fn (Builder $p) => $p->orWhereMorphedTo('subject', $pass)))
                ->with('causer')
                ->latest('id')
                ->get(),
        ]);
    }

    /**
     * Reprint of a receipt from the back office (counter sales and imported history).
     */
    public function print(Request $request, Payment $payment, PassSigner $signer): View
    {
        Gate::authorize('subscriptions.view-all');
        abort_unless(in_array($payment->gateway, ['counter', 'import'], true) && $payment->payable !== null, 404);

        $width = in_array($request->integer('largeur'), [58, 80], true)
            ? $request->integer('largeur')
            : (int) config('transport.counter.receipt_width', 80);

        return view('guichet.sales.receipt', [
            'payment' => $payment->load(['zone.line', 'stop', 'seller', 'subscriber', 'user']),
            'pass' => $payment->payable,
            'qr' => $payment->isSucceeded() ? $signer->sign($payment->payable) : null,
            'width' => $width,
            'autoPrint' => $request->boolean('imprimer'),
            'back' => route('admin.receipts.show', $payment),
        ]);
    }

    /**
     * @return array{month: string|null, q: string|null, method: string|null, kind: string, status: string, zone: int|null}
     */
    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'month' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'q' => ['nullable', 'string', 'max:100'],
            'method' => ['nullable', 'string', 'in:'.implode(',', array_map(fn (PaymentMethod $m) => $m->value, PaymentMethod::cases()))],
            'kind' => ['nullable', 'in:subscriptions,tickets,all'],
            'status' => ['nullable', 'in:paid,cancelled,unpaid,all'],
            'zone' => ['nullable', 'integer'],
        ]);

        return [
            // Current month by default; "Tous les mois" sends an empty value.
            'month' => $request->has('month') ? ($validated['month'] ?? null) : today()->format('Y-m'),
            'q' => $validated['q'] ?? null,
            'method' => $validated['method'] ?? null,
            'kind' => $validated['kind'] ?? 'subscriptions',
            'status' => $validated['status'] ?? 'paid',
            'zone' => isset($validated['zone']) ? (int) $validated['zone'] : null,
        ];
    }

    /**
     * Payments matching the filters (paid ones by default).
     *
     * @param  array{month: string|null, q: string|null, method: string|null, kind: string, status: string, zone: int|null}  $filters
     * @return Builder<Payment>
     */
    private function query(array $filters): Builder
    {
        $month = SubscriptionMonths::parse($filters['month']);

        return Payment::query()
            ->when($filters['status'] === 'paid', fn (Builder $q) => $q->where('status', PaymentStatus::Succeeded)->whereNotNull('receipt_number'))
            ->when($filters['status'] === 'cancelled', fn (Builder $q) => $q->where('status', PaymentStatus::Cancelled))
            ->when($filters['status'] === 'unpaid', fn (Builder $q) => $q->whereIn('status', [PaymentStatus::Pending, PaymentStatus::Failed]))
            ->when($filters['zone'], fn (Builder $q, int $zone) => $q->where('zone_id', $zone))
            ->when($filters['kind'] === 'subscriptions', fn (Builder $q) => $q->whereIn('purpose', array_map(fn (FareType $t) => $t->value, FareType::subscriptions())))
            ->when($filters['kind'] === 'tickets', fn (Builder $q) => $q->where('purpose', FareType::Daily))
            ->when($month, function (Builder $q, Carbon $month) {
                // Subscriptions: the month they cover. Tickets: the month they were sold.
                $q->where(fn (Builder $w) => $w->whereDate('period_month', $month->toDateString())
                    ->orWhere(fn (Builder $t) => $t->whereNull('period_month')
                        ->whereBetween('created_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])));
            })
            ->when($filters['method'], fn (Builder $q, string $method) => $q->where('payment_method', $method))
            ->when($filters['q'], function (Builder $q, string $search) {
                $phone = PhoneNumber::normalize($search);
                $q->where(fn (Builder $w) => $w->where('receipt_number', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhere('external_reference', 'like', "%{$search}%")
                    ->orWhereHas('subscriber', fn (Builder $s) => $s->withTrashed()->where('full_name', 'like', "%{$search}%")
                        ->when($phone, fn (Builder $p) => $p->orWhere('phone', $phone))));
            })
            ->orderByRaw('COALESCE(confirmed_at, created_at) DESC')
            ->orderByDesc('id');
    }

    /**
     * @return array<string, string>
     */
    private function monthOptions(): array
    {
        $options = ['' => 'Tous les mois'];
        $month = today()->startOfMonth()->addMonth();

        for ($i = 0; $i < 13; $i++) {
            $options[$month->format('Y-m')] = ucfirst(SubscriptionMonths::label($month));
            $month->subMonthNoOverflow();
        }

        return $options;
    }
}
