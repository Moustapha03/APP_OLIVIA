<?php

namespace App\Models;

use App\Enums\Period;
use App\Enums\ScanResult;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One pass check made on board by a controller (VerifyPass), kept for
 * anti-replay, refunds (rule 7: "never scanned") and reports.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $kind ticket | subscription | unknown
 * @property int|null $ticket_id
 * @property int|null $subscription_id
 * @property ScanResult $result
 * @property string $reason_code
 * @property string $reason
 * @property Period|null $period
 * @property string $source online | offline (synced afterwards)
 * @property string|null $client_id UUID made by the phone (offline scans)
 * @property ScanResult|null $device_result what the phone showed (offline scans)
 * @property string|null $device_reason_code
 * @property Carbon $scanned_at
 * @property Carbon|null $synced_at
 * @property Carbon $created_at
 * @property-read User|null $user
 * @property-read Ticket|null $ticket
 * @property-read Subscription|null $subscription
 */
class Scan extends Model
{
    public const KIND_TICKET = 'ticket';

    public const KIND_SUBSCRIPTION = 'subscription';

    public const KIND_UNKNOWN = 'unknown';

    public const SOURCE_ONLINE = 'online';

    public const SOURCE_OFFLINE = 'offline';

    /**
     * Reason codes that let the passenger board (anti-replay looks for them).
     */
    public const ACCEPTED_CODES = ['ok', 'photo_missing'];

    /**
     * @var list<string>
     */
    protected $fillable = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'result' => ScanResult::class,
            'period' => Period::class,
            'device_result' => ScanResult::class,
            'scanned_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    /**
     * The controller who scanned.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Scans that let the passenger board.
     *
     * @param  Builder<Scan>  $query
     */
    public function scopeAccepted(Builder $query): void
    {
        $query->whereIn('reason_code', self::ACCEPTED_CODES);
    }
}
