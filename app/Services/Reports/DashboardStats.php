<?php

namespace App\Services\Reports;

use App\Enums\FareType;
use App\Enums\PassStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ScanResult;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Scan;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Figures of the back-office dashboard for one calendar month.
 *
 * Revenue = paid receipts (cancelled sales excluded) on their payment date
 * (cash basis: next month's subscription paid on the 28th counts on the
 * 28th). Aggregation is done in PHP on a month of rows: same result on
 * SQLite (tests) and MariaDB, and small volumes for an agency.
 */
class DashboardStats
{
    private readonly Carbon $start;

    private readonly Carbon $end;

    /** @var Collection<int, Payment>|null */
    private ?Collection $payments = null;

    public function __construct(Carbon $month)
    {
        $this->start = $month->copy()->startOfMonth();
        $this->end = $month->copy()->endOfMonth();
    }

    public function start(): Carbon
    {
        return $this->start;
    }

    /**
     * Key figures, with the previous month for comparison.
     *
     * @return array{revenue: int, previous_revenue: int, receipts: int, subscriptions_revenue: int, tickets_revenue: int, tickets: int, active_subscribers: int, previous_active_subscribers: int, average: int, discounts: int, discount_count: int, cash: int, transfers: int, to_renew: int, expenses: int, previous_expenses: int, net: int}
     */
    public function kpis(): array
    {
        $payments = $this->payments();
        $previous = $this->paidBetween($this->start->copy()->subMonthNoOverflow()->startOfMonth(), $this->start->copy()->subMonthNoOverflow()->endOfMonth());
        $revenue = (int) $payments->sum('amount');
        $subscriptions = $payments->filter(fn (Payment $p) => $p->purpose->isSubscription());
        $discounted = $payments->filter(fn (Payment $p) => $p->amount < $p->list_amount);

        return [
            'revenue' => $revenue,
            'previous_revenue' => (int) $previous->sum('amount'),
            'receipts' => $payments->count(),
            'subscriptions_revenue' => (int) $subscriptions->sum('amount'),
            'tickets_revenue' => $revenue - (int) $subscriptions->sum('amount'),
            'tickets' => $payments->count() - $subscriptions->count(),
            'active_subscribers' => $this->activeSubscribers($this->start),
            'previous_active_subscribers' => $this->activeSubscribers($this->start->copy()->subMonthNoOverflow()),
            'average' => $payments->isEmpty() ? 0 : (int) round($revenue / $payments->count()),
            'discounts' => (int) $discounted->sum(fn (Payment $p) => $p->list_amount - $p->amount),
            'discount_count' => $discounted->count(),
            'cash' => (int) $payments->where('payment_method', PaymentMethod::Cash)->sum('amount'),
            'transfers' => $revenue - (int) $payments->where('payment_method', PaymentMethod::Cash)->sum('amount'),
            'to_renew' => $this->toRenewCount(),
            'expenses' => $expenses = $this->expensesBetween($this->start, $this->end),
            'previous_expenses' => $this->expensesBetween($this->start->copy()->subMonthNoOverflow()->startOfMonth(), $this->start->copy()->subMonthNoOverflow()->endOfMonth()),
            'net' => $revenue - $expenses,
        ];
    }

    /**
     * Expenses of the month by type (archived expenses excluded).
     *
     * @return array<string, int> type => amount
     */
    public function expensesByCategory(): array
    {
        return Expense::query()
            ->whereBetween('spent_on', [$this->start->toDateString(), $this->end->toDateString()])
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->selectRaw('expense_categories.name as name, SUM(expenses.amount) as total')
            ->groupBy('expense_categories.name')
            ->orderByDesc('total')
            ->pluck('total', 'name')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    private function expensesBetween(Carbon $from, Carbon $to): int
    {
        return (int) Expense::query()->whereBetween('spent_on', [$from->toDateString(), $to->toDateString()])->sum('amount');
    }

    /**
     * Revenue per day of the month, subscriptions and tickets apart.
     *
     * @return array{labels: list<string>, subscriptions: list<int>, tickets: list<int>}
     */
    public function daily(): array
    {
        $byDay = $this->payments()->groupBy(fn (Payment $p) => $this->paidAt($p)->day);
        $series = ['labels' => [], 'subscriptions' => [], 'tickets' => []];

        for ($day = 1; $day <= $this->end->day; $day++) {
            $rows = $byDay->get($day, collect());
            $series['labels'][] = (string) $day;
            $series['subscriptions'][] = (int) $rows->filter(fn (Payment $p) => $p->purpose->isSubscription())->sum('amount');
            $series['tickets'][] = (int) $rows->reject(fn (Payment $p) => $p->purpose->isSubscription())->sum('amount');
        }

        return $series;
    }

    /**
     * Revenue and expenses of the 12 months ending with this one.
     *
     * @return array{labels: list<string>, subscriptions: list<int>, tickets: list<int>, expenses: list<int>}
     */
    public function monthly(): array
    {
        $first = $this->start->copy()->subMonthsNoOverflow(11);
        $byMonth = $this->paidBetween($first, $this->end)->groupBy(fn (Payment $p) => $this->paidAt($p)->format('Y-m'));
        $expenses = Expense::query()
            ->whereBetween('spent_on', [$first->toDateString(), $this->end->toDateString()])
            ->get(['spent_on', 'amount'])
            ->groupBy(fn (Expense $e) => $e->spent_on->format('Y-m'));
        $series = ['labels' => [], 'subscriptions' => [], 'tickets' => [], 'expenses' => []];

        for ($month = $first->copy(); $month->lte($this->start); $month->addMonthNoOverflow()) {
            $rows = $byMonth->get($month->format('Y-m'), collect());
            $series['labels'][] = ucfirst($month->translatedFormat('M y'));
            $series['subscriptions'][] = (int) $rows->filter(fn (Payment $p) => $p->purpose->isSubscription())->sum('amount');
            $series['tickets'][] = (int) $rows->reject(fn (Payment $p) => $p->purpose->isSubscription())->sum('amount');
            $series['expenses'][] = (int) $expenses->get($month->format('Y-m'), collect())->sum('amount');
        }

        return $series;
    }

    /**
     * @return array<string, int> payment method label => amount
     */
    public function byMethod(): array
    {
        return $this->payments()
            ->groupBy(fn (Payment $p) => $p->payment_method?->label() ?? 'Autre')
            ->map(fn (Collection $rows) => (int) $rows->sum('amount'))
            ->sortDesc()
            ->all();
    }

    /**
     * @return array<string, int> zone => amount
     */
    public function byZone(): array
    {
        return $this->payments()
            ->groupBy(fn (Payment $p) => $p->zone->name ?? '—')
            ->map(fn (Collection $rows) => (int) $rows->sum('amount'))
            ->sortDesc()
            ->all();
    }

    /**
     * Subscriptions covering the month, by formula.
     *
     * @return array<string, int> formula => count
     */
    public function byFormula(): array
    {
        $counts = Subscription::query()
            ->where('status', PassStatus::Active)
            ->whereDate('starts_on', '<=', $this->end->toDateString())
            ->whereDate('ends_on', '>=', $this->start->toDateString())
            ->pluck('formula')
            ->countBy(fn (FareType $type) => $type->shortLabel());

        return collect(FareType::subscriptions())
            ->mapWithKeys(fn (FareType $type) => [$type->shortLabel() => (int) $counts->get($type->shortLabel(), 0)])
            ->all();
    }

    /**
     * Busiest boarding points of the month.
     *
     * @return list<array{name: string, zone: string, count: int, amount: int}>
     */
    public function topStops(int $limit = 8): array
    {
        return $this->payments()
            ->groupBy(fn (Payment $p) => $p->stop->name ?? $p->zone->name ?? '—')
            ->map(fn (Collection $rows, string $name) => [
                'name' => $name,
                'zone' => (string) ($rows->first()->zone->name ?? ''),
                'count' => $rows->count(),
                'amount' => (int) $rows->sum('amount'),
            ])
            ->sortByDesc('amount')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * Today's controls on board.
     *
     * @return array{valid: int, warning: int, refused: int}
     */
    public function scansToday(): array
    {
        $counts = Scan::query()
            ->whereBetween('scanned_at', [today(), today()->endOfDay()])
            ->pluck('result')
            ->countBy(fn (ScanResult $result) => $result->value);

        return [
            'valid' => (int) $counts->get('valid', 0),
            'warning' => (int) $counts->get('warning', 0),
            'refused' => (int) $counts->get('refused', 0),
        ];
    }

    /**
     * @return Collection<int, Payment>
     */
    public function latest(int $limit = 8): Collection
    {
        return Payment::query()
            ->where('status', PaymentStatus::Succeeded)
            ->whereNotNull('receipt_number')
            ->with(['subscriber', 'zone'])
            ->orderByRaw('COALESCE(confirmed_at, created_at) DESC')
            ->limit($limit)
            ->get();
    }

    /**
     * Subscribers of the previous month without a subscription this month.
     */
    private function toRenewCount(): int
    {
        $previous = $this->start->copy()->subMonthNoOverflow();
        $covered = fn (Carbon $month) => Subscription::query()
            ->where('status', PassStatus::Active)
            ->whereDate('starts_on', '<=', $month->copy()->endOfMonth()->toDateString())
            ->whereDate('ends_on', '>=', $month->copy()->startOfMonth()->toDateString())
            ->select('subscriber_id');

        return Subscription::query()
            ->whereIn('subscriber_id', $covered($previous))
            ->whereNotIn('subscriber_id', $covered($this->start))
            ->whereHas('subscriber')
            ->distinct()
            ->count('subscriber_id');
    }

    private function activeSubscribers(Carbon $month): int
    {
        return Subscription::query()
            ->where('status', PassStatus::Active)
            ->whereDate('starts_on', '<=', $month->copy()->endOfMonth()->toDateString())
            ->whereDate('ends_on', '>=', $month->copy()->startOfMonth()->toDateString())
            ->distinct()
            ->count('subscriber_id');
    }

    /**
     * @return Collection<int, Payment>
     */
    private function payments(): Collection
    {
        return $this->payments ??= $this->paidBetween($this->start, $this->end);
    }

    /**
     * @return Collection<int, Payment>
     */
    private function paidBetween(Carbon $from, Carbon $to): Collection
    {
        return Payment::query()
            ->where('status', PaymentStatus::Succeeded)
            ->where(fn (Builder $q) => $q->whereBetween('confirmed_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
                ->orWhere(fn (Builder $w) => $w->whereNull('confirmed_at')->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])))
            ->with(['zone', 'stop'])
            ->get(['id', 'purpose', 'amount', 'list_amount', 'payment_method', 'zone_id', 'stop_id', 'confirmed_at', 'created_at']);
    }

    private function paidAt(Payment $payment): Carbon
    {
        return $payment->confirmed_at ?? $payment->created_at;
    }
}
