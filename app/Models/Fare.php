<?php

namespace App\Models;

use App\Enums\FareType;
use Database\Factories\FareFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Price of a zone for a formula, valid from effective_from to
 * effective_to (inclusive, null = no end). Never edited once in force:
 * see App\Actions\Fares\ScheduleFare. The price is copied on each pass
 * at purchase time, so a new fare never changes passes already sold.
 *
 * @property int $id
 * @property int $zone_id
 * @property FareType $type
 * @property int $amount
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 * @property int|null $created_by
 * @property-read Zone $zone
 */
class Fare extends Model
{
    /** @use HasFactory<FareFactory> */
    use HasFactory, LogsActivity;

    /**
     * Fares are only created through ScheduleFare (never mass assigned
     * from a form), hence an empty list.
     *
     * @var list<string>
     */
    protected $fillable = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => FareType::class,
            'amount' => 'integer',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Zone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @param  Builder<Fare>  $query
     */
    public function scopeOfType(Builder $query, FareType $type): void
    {
        $query->where('type', $type);
    }

    /**
     * @param  Builder<Fare>  $query
     */
    public function scopeInForceOn(Builder $query, Carbon $date): void
    {
        $day = $date->toDateString();

        $query->whereDate('effective_from', '<=', $day)
            ->where(fn (Builder $q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $day))
            ->orderByDesc('effective_from');
    }

    /**
     * @param  Builder<Fare>  $query
     */
    public function scopeScheduled(Builder $query): void
    {
        $query->whereDate('effective_from', '>', today()->toDateString());
    }

    public function isScheduled(): bool
    {
        return $this->effective_from->isAfter(today());
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('reference')
            ->logOnly(['zone_id', 'type', 'amount', 'effective_from', 'effective_to'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
