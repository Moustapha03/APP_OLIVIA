<?php

namespace App\Services\Receipts;

use App\Enums\FareType;
use Illuminate\Support\Facades\DB;

/**
 * Human receipt numbers, as the agency already uses them:
 *   BA-2026-0018   subscription ("bon d'abonnement")
 *   TJ-2026-00001  daily ticket
 * One gapless series per prefix and year. Must be called inside the
 * transaction that confirms the payment: the sequence row is locked, so
 * two desks can never get the same number.
 */
class ReceiptNumbers
{
    public function next(FareType $type, ?int $year = null): string
    {
        $prefix = $type->isSubscription() ? 'BA' : 'TJ';
        $digits = $type->isSubscription() ? 4 : 5;
        $year ??= (int) now()->format('Y');

        DB::table('receipt_sequences')->insertOrIgnore([
            'prefix' => $prefix,
            'year' => $year,
            'last_number' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = DB::table('receipt_sequences')->where('prefix', $prefix)->where('year', $year)->lockForUpdate()->first();
        $number = (int) $row->last_number + 1;

        DB::table('receipt_sequences')->where('id', $row->id)->update(['last_number' => $number, 'updated_at' => now()]);

        return sprintf('%s-%d-%0'.$digits.'d', $prefix, $year, $number);
    }

    /**
     * After importing existing receipts: continue after the highest number.
     */
    public function bumpTo(string $prefix, int $year, int $number): void
    {
        DB::table('receipt_sequences')->insertOrIgnore([
            'prefix' => $prefix, 'year' => $year, 'last_number' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('receipt_sequences')->where('prefix', $prefix)->where('year', $year)
            ->where('last_number', '<', $number)
            ->update(['last_number' => $number, 'updated_at' => now()]);
    }
}
