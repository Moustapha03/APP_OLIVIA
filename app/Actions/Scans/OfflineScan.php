<?php

namespace App\Actions\Scans;

use App\Enums\ScanResult;
use Illuminate\Support\Carbon;

/**
 * A scan made on board without network, as sent by the phone afterwards
 * (SyncOfflineScans). VerifyPass re-runs its checks at $scannedAt.
 */
final readonly class OfflineScan
{
    /**
     * Reason codes the phone gives when it lets the passenger board
     * (resources/js/controle/offline.js).
     */
    public const DEVICE_ACCEPTED_CODES = ['ok', 'photo_missing', 'photo_offline'];

    /**
     * Every reason code the phone may send.
     */
    public const DEVICE_REASON_CODES = ['ok', 'photo_missing', 'photo_offline', 'replay', 'unknown', 'wrong_period', 'used_up', 'manual_entry'];

    public function __construct(
        public string $clientId,
        public Carbon $scannedAt,
        public ScanResult $deviceResult,
        public string $deviceReasonCode,
    ) {}

    /**
     * Did the controller let the passenger board?
     */
    public function deviceAccepted(): bool
    {
        return $this->deviceResult !== ScanResult::Refused
            && in_array($this->deviceReasonCode, self::DEVICE_ACCEPTED_CODES, true);
    }
}
