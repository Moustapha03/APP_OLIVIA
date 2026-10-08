<?php

namespace App\Enums;

/**
 * Outcome of a pass check on board (scans.result).
 * Warning = the passenger may board, but the controller must look twice.
 */
enum ScanResult: string
{
    case Valid = 'valid';
    case Refused = 'refused';
    case Warning = 'warning';

    public function label(): string
    {
        return match ($this) {
            self::Valid => 'Valide',
            self::Refused => 'Refusé',
            self::Warning => 'Attention',
        };
    }

    /**
     * <x-badge> variant.
     */
    public function variant(): string
    {
        return match ($this) {
            self::Valid => 'success',
            self::Refused => 'danger',
            self::Warning => 'warning',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Valid => 'circle-check',
            self::Refused => 'circle-x',
            self::Warning => 'triangle-alert',
        };
    }
}
