<?php

namespace App\Models;

use App\Enums\FareType;
use Database\Factories\ZoneFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Fare zone, e.g. "Bonoua / Bassam". Groups boarding points that pay the
 * same price. Position 1 is the closest to the destination (Angré).
 *
 * @property int $id
 * @property int $line_id
 * @property string $name
 * @property int $position
 * @property bool $is_active
 * @property-read Line $line
 */
class Zone extends Model
{
    /** @use HasFactory<ZoneFactory> */
    use HasFactory, LogsActivity;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Line, $this>
     */
    public function line(): BelongsTo
    {
        return $this->belongsTo(Line::class);
    }

    /**
     * Boarding points of the zone.
     *
     * @return HasMany<Stop, $this>
     */
    public function stops(): HasMany
    {
        return $this->hasMany(Stop::class)->orderBy('name');
    }

    /**
     * @return HasMany<Fare, $this>
     */
    public function fares(): HasMany
    {
        return $this->hasMany(Fare::class);
    }

    /**
     * @param  Builder<Zone>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Fare in force on a date (today by default), or null.
     */
    public function fareOn(FareType $type, ?Carbon $date = null): ?Fare
    {
        return $this->fares()->ofType($type)->inForceOn($date ?? today())->first();
    }

    /**
     * Business rule 3: a pass of $paidZone is valid when boarding in that
     * zone or any zone closer to the destination (same line).
     */
    public function isCoveredBy(Zone $paidZone): bool
    {
        return $this->line_id === $paidZone->line_id && $this->position <= $paidZone->position;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('reference')->logOnly(['name', 'position', 'is_active', 'line_id'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
