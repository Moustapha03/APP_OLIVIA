<?php

namespace App\Actions\Scans;

use App\Enums\ScanResult;
use App\Models\Scan;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;

/**
 * Records the scans a controller made without network (step 7), sent by
 * the phone once back online (POST /api/controle/scans/sync).
 *
 * - each scan goes through VerifyPass at the time it was made (signature of
 *   the QR code, dates, period, anti-replay, rides left);
 * - a ride is counted on a ticket only when the phone accepted the passenger
 *   and a ride is left (row locked, as online), and only on the ticket's
 *   own day: a scan received after that day is recorded without counting
 *   a ride ("offline_late");
 * - scans older than 24 h or more than 10 min ahead are refused
 *   (SyncScansRequest): a back-dated queue cannot rewrite history;
 * - idempotent: each scan carries a UUID made by the phone (client_id,
 *   unique); a scan already received is answered "duplicate", nothing new
 *   is recorded, so the phone can safely send a batch again.
 */
class SyncOfflineScans
{
    public function __construct(private readonly VerifyPass $verify) {}

    /**
     * @param  list<array{client_id: string, code: string, result: string, reason_code: string, scanned_at: string}>  $items
     * @return list<array{client_id: string, status: string, result: string|null, reason_code: string|null}>
     */
    public function handle(User $controller, array $items): array
    {
        // Chronological order: the anti-replay looks at earlier scans.
        usort($items, fn (array $a, array $b): int => strcmp(
            self::scannedAt($a['scanned_at'])->toIso8601String(),
            self::scannedAt($b['scanned_at'])->toIso8601String(),
        ));

        $results = [];

        foreach ($items as $item) {
            $results[] = $this->one($controller, $item);
        }

        $created = count(array_filter($results, fn (array $r): bool => $r['status'] === 'created'));

        if ($results !== []) {
            activity('audit')->causedBy($controller)
                ->event('scans.sync')
                ->withProperties(['received' => count($results), 'created' => $created])
                ->log('Scans hors ligne envoyés');
        }

        return $results;
    }

    /**
     * Times sent by the phone are ISO 8601 (UTC): stored in the app timezone.
     */
    public static function scannedAt(string $value): Carbon
    {
        return Carbon::parse($value)->setTimezone((string) config('app.timezone'));
    }

    /**
     * @param  array{client_id: string, code: string, result: string, reason_code: string, scanned_at: string}  $item
     * @return array{client_id: string, status: string, result: string|null, reason_code: string|null}
     */
    private function one(User $controller, array $item): array
    {
        $clientId = strtolower($item['client_id']);
        $existing = Scan::query()->where('client_id', $clientId)->first();

        if ($existing !== null) {
            return self::answer($clientId, 'duplicate', $existing);
        }

        $offline = new OfflineScan(
            clientId: $clientId,
            scannedAt: self::scannedAt($item['scanned_at']),
            deviceResult: ScanResult::from($item['result']),
            deviceReasonCode: $item['reason_code'],
        );

        try {
            $check = $this->verify->handle($controller, $item['code'], $offline);
        } catch (UniqueConstraintViolationException) {
            // Same batch sent twice at the same moment: the other request won.
            return self::answer($clientId, 'duplicate', Scan::query()->where('client_id', $clientId)->first());
        }

        return self::answer($clientId, 'created', $check->scan);
    }

    /**
     * @return array{client_id: string, status: string, result: string|null, reason_code: string|null}
     */
    private static function answer(string $clientId, string $status, ?Scan $scan): array
    {
        return [
            'client_id' => $clientId,
            'status' => $status,
            'result' => $scan?->result->value,
            'reason_code' => $scan?->reason_code,
        ];
    }
}
