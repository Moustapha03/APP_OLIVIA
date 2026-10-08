<?php

namespace App\Http\Requests\Api\Controle;

use App\Actions\Scans\OfflineScan;
use App\Enums\Period;
use App\Enums\ScanResult;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Batch of offline scans sent by the controller's phone (at most
 * MAX_SCANS per request; the phone splits larger queues).
 */
class SyncScansRequest extends FormRequest
{
    public const MAX_SCANS = 200;

    /**
     * How old an offline scan may be when it is received.
     */
    public const MAX_AGE_HOURS = 24;

    /**
     * Clock difference tolerated with the phone (scan "in the future").
     */
    public const MAX_AHEAD_MINUTES = 10;

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('scans.create');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'scans' => ['required', 'array', 'list', 'min:1', 'max:'.self::MAX_SCANS],
            'scans.*' => ['required', 'array:client_id,code,result,reason_code,scanned_at,period'],
            'scans.*.client_id' => ['required', 'uuid', 'distinct:ignore_case'],
            'scans.*.code' => ['required', 'string', 'max:200'],
            'scans.*.result' => ['required', Rule::enum(ScanResult::class)],
            'scans.*.reason_code' => ['required', 'string', Rule::in(OfflineScan::DEVICE_REASON_CODES)],
            'scans.*.scanned_at' => [
                'required', 'date',
                'after_or_equal:'.now()->subHours(self::MAX_AGE_HOURS)->toIso8601String(),
                // A few minutes of clock difference with the phone are tolerated.
                'before_or_equal:'.now()->addMinutes(self::MAX_AHEAD_MINUTES)->toIso8601String(),
            ],
            'scans.*.period' => ['nullable', Rule::enum(Period::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'scans.max' => 'Au plus '.self::MAX_SCANS.' scans par envoi.',
            'scans.*.scanned_at.after_or_equal' => 'Scan trop ancien (plus de '.self::MAX_AGE_HOURS.' heures).',
            'scans.*.scanned_at.before_or_equal' => 'Heure du scan dans le futur : vérifiez l’heure du téléphone.',
        ];
    }

    /**
     * @return list<array{client_id: string, code: string, result: string, reason_code: string, scanned_at: string}>
     */
    public function scans(): array
    {
        /** @var list<array{client_id: string, code: string, result: string, reason_code: string, scanned_at: string}> $scans */
        $scans = $this->validated('scans');

        return $scans;
    }
}
