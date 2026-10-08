<?php

namespace App\Actions\Cash;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\CashSession;

/**
 * Totals of a register. Only CASH sales go in the drawer: Mobile Money
 * transfers (Wave…) are counted apart, for reconciliation with the
 * company account. Cancelled sales were handed back: not counted.
 */
final readonly class CashSessionTotals
{
    /**
     * @param  array<string, int>  $transfersByMethod  method label => amount
     */
    public function __construct(
        public int $openingFloat,
        public int $salesCount,
        public int $cashAmount,
        public int $transferAmount,
        public array $transfersByMethod,
        public int $ticketCount,
        public int $subscriptionCount,
        public int $cancelledCount,
        public int $cancelledAmount,
        public int $discountCount,
    ) {}

    public static function for(CashSession $session): self
    {
        $rows = $session->payments()
            ->selectRaw('status, purpose, payment_method, COUNT(*) as n, COALESCE(SUM(amount), 0) as total, SUM(CASE WHEN amount <> list_amount THEN 1 ELSE 0 END) as discounted')
            ->groupBy('status', 'purpose', 'payment_method')
            ->get();

        $paid = $rows->filter(fn ($r) => $r->status === PaymentStatus::Succeeded);
        $cancelled = $rows->filter(fn ($r) => $r->status === PaymentStatus::Cancelled);
        $cash = $paid->filter(fn ($r) => $r->payment_method === PaymentMethod::Cash);
        $transfers = $paid->filter(fn ($r) => $r->payment_method?->isTransfer() === true);

        $byMethod = [];
        foreach ($transfers as $row) {
            $label = $row->payment_method->label();
            $byMethod[$label] = ($byMethod[$label] ?? 0) + (int) $row->getAttribute('total');
        }

        return new self(
            openingFloat: $session->opening_float,
            salesCount: (int) $paid->sum('n'),
            cashAmount: (int) $cash->sum('total'),
            transferAmount: (int) $transfers->sum('total'),
            transfersByMethod: $byMethod,
            ticketCount: (int) $paid->filter(fn ($r) => ! $r->purpose->isSubscription())->sum('n'),
            subscriptionCount: (int) $paid->filter(fn ($r) => $r->purpose->isSubscription())->sum('n'),
            cancelledCount: (int) $cancelled->sum('n'),
            cancelledAmount: (int) $cancelled->sum('total'),
            discountCount: (int) $paid->sum('discounted'),
        );
    }

    /**
     * Cash that should be in the drawer.
     */
    public function expected(): int
    {
        return $this->openingFloat + $this->cashAmount;
    }

    public function salesAmount(): int
    {
        return $this->cashAmount + $this->transferAmount;
    }
}
