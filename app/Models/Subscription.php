<?php

namespace App\Models;

use App\Contracts\Pass;
use App\Enums\FareType;
use App\Enums\PassChannel;
use App\Enums\PassStatus;
use App\Enums\Period;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Monthly subscription of a subscriber for a CALENDAR month ("octobre
 * 2026"), formula matin & soir / matin / soir. Nominative (photo shown at
 * control), valid in its zone and every zone closer to the destination
 * (rule 3). Its price is frozen at purchase (rule 6).
 *
 * @property int $id
 * @property string $uuid
 * @property int $subscriber_id
 * @property int|null $user_id
 * @property int $zone_id
 * @property int|null $stop_id
 * @property int|null $fare_id
 * @property int|null $payment_id
 * @property FareType $formula
 * @property int $price_paid
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property PassChannel $channel
 * @property PassStatus $status
 * @property int|null $sold_by
 * @property Carbon $created_at
 * @property-read Subscriber $subscriber
 * @property-read Zone $zone
 * @property-read Stop|null $stop
 * @property-read User|null $user
 * @property-read Payment|null $payment
 */
class Subscription extends Model implements Pass
{
    /** @use HasFactory<SubscriptionFactory> */
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
            'formula' => FareType::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
            'price_paid' => 'integer',
            'channel' => PassChannel::class,
            'status' => PassStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Subscriber, $this>
     */
    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Subscriber::class)->withTrashed();
    }

    /**
     * Account that bought it.
     *
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
     * Boarding point chosen at purchase.
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
     * Not cancelled and not over yet (current or starting later).
     *
     * @param  Builder<Subscription>  $query
     */
    public function scopeRunning(Builder $query, ?Carbon $date = null): void
    {
        $query->where('status', PassStatus::Active)->whereDate('ends_on', '>=', ($date ?? today())->toDateString());
    }

    /**
     * Subscriptions of a calendar month.
     *
     * @param  Builder<Subscription>  $query
     */
    public function scopeForMonth(Builder $query, Carbon $month): void
    {
        $query->whereDate('starts_on', $month->copy()->startOfMonth()->toDateString());
    }

    public function isActiveOn(?Carbon $date = null): bool
    {
        $date ??= today();

        return $this->status === PassStatus::Active
            && $this->starts_on->lte($date)
            && $this->ends_on->gte($date->copy()->startOfDay());
    }

    public function hasStarted(?Carbon $date = null): bool
    {
        return $this->starts_on->lte($date ?? today());
    }

    public function isExpired(?Carbon $date = null): bool
    {
        return $this->ends_on->lt(($date ?? today())->copy()->startOfDay());
    }

    /**
     * Days left including today (0 once expired).
     */
    public function daysLeft(?Carbon $date = null): int
    {
        $date = ($date ?? today())->copy()->startOfDay();

        return $this->ends_on->lt($date) ? 0 : (int) $date->diffInDays($this->ends_on) + 1;
    }

    public function expiresSoon(int $days = 3, ?Carbon $date = null): bool
    {
        return $this->isActiveOn($date) && $this->daysLeft($date) <= $days;
    }

    public function coversPeriod(Period $period): bool
    {
        return in_array($period, $this->formula->periods(), true);
    }

    /**
     * "octobre 2026"
     */
    public function monthLabel(): string
    {
        return $this->starts_on->translatedFormat('F Y');
    }

    public function passKind(): string
    {
        return 's';
    }

    public function passUuid(): string
    {
        return $this->uuid;
    }
}
