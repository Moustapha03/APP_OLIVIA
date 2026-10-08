<?php

namespace App\Models;

use App\Contracts\Pass;
use App\Enums\PassChannel;
use App\Enums\PassStatus;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Daily ticket: valid on its day until 23:59 (Africa/Abidjan), for a
 * number of rides (business rule 2). Issued by IssuePass only.
 *
 * @property int $id
 * @property string $uuid
 * @property int|null $user_id
 * @property int $zone_id
 * @property int|null $stop_id
 * @property int|null $fare_id
 * @property int|null $payment_id
 * @property int $price_paid
 * @property Carbon $valid_on
 * @property int $rides_allowed
 * @property int $rides_used
 * @property PassChannel $channel
 * @property PassStatus $status
 * @property int|null $sold_by
 * @property Carbon $created_at
 * @property-read Zone $zone
 * @property-read Stop|null $stop
 * @property-read User|null $user
 */
class Ticket extends Model implements Pass
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory;

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
            'valid_on' => 'date',
            'price_paid' => 'integer',
            'rides_allowed' => 'integer',
            'rides_used' => 'integer',
            'channel' => PassChannel::class,
            'status' => PassStatus::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Zone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /**
     * Boarding point chosen at purchase (informative).
     *
     * @return BelongsTo<Stop, $this>
     */
    public function stop(): BelongsTo
    {
        return $this->belongsTo(Stop::class);
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @param  Builder<Ticket>  $query
     */
    public function scopeValidOn(Builder $query, Carbon $date): void
    {
        $query->where('status', PassStatus::Active)->whereDate('valid_on', $date->toDateString());
    }

    public function ridesLeft(): int
    {
        return max(0, $this->rides_allowed - $this->rides_used);
    }

    public function isExpired(?Carbon $at = null): bool
    {
        return $this->valid_on->lt(($at ?? today())->copy()->startOfDay());
    }

    public function isUsable(?Carbon $at = null): bool
    {
        $at ??= now();

        return $this->status === PassStatus::Active
            && $this->valid_on->isSameDay($at)
            && $this->ridesLeft() > 0;
    }

    public function passKind(): string
    {
        return 't';
    }

    public function passUuid(): string
    {
        return $this->uuid;
    }
}
