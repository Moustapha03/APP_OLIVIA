<?php

namespace App\Actions\Scans;

use App\Enums\ScanResult;
use App\Models\Scan;

/**
 * What the controller sees after a scan (VerifyPass), in plain French.
 * Every optional field is null when unknown (forged code…).
 */
final readonly class PassCheck
{
    public function __construct(
        public ScanResult $status,
        public string $title,
        public string $reason,
        public Scan $scan,
        public ?string $holder = null,
        public ?string $photoUrl = null,
        public ?string $formula = null,
        public ?string $zone = null,
        public ?string $boardingPoint = null,
        public ?string $validity = null,
        public ?string $rides = null,
        public ?string $receiptNumber = null,
    ) {}

    /**
     * Details shown under the verdict, label => value (empty values left out).
     *
     * @return array<string, string>
     */
    public function details(): array
    {
        return array_filter([
            'Formule' => $this->formula,
            'Zone' => $this->zone,
            'Montée' => $this->boardingPoint,
            'Validité' => $this->validity,
            'Trajets' => $this->rides,
            'Reçu' => $this->receiptNumber,
        ], fn (?string $value): bool => $value !== null && $value !== '');
    }
}
