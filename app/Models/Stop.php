<?php

namespace App\Models;

use Database\Factories\StopFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Boarding point ("point de montée"), e.g. Zouglou, Carrefour Aéroport.
 * Its price is the price of its zone.
 *
 * @property int $id
 * @property int $zone_id
 * @property string $name
 * @property bool $is_active
 * @property-read Zone $zone
 */
class Stop extends Model
{
    /** @use HasFactory<StopFactory> */
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
        return ['is_active' => 'boolean'];
    }

    /**
     * @return BelongsTo<Zone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /**
     * @param  Builder<Stop>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Can be sold: the point, its zone and its line are active.
     */
    public function isSellable(): bool
    {
        $this->loadMissing('zone.line');

        return $this->is_active && $this->zone->is_active && $this->zone->line->is_active;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('reference')->logOnly(['name', 'is_active', 'zone_id'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
