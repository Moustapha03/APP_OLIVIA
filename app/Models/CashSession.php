<?php

namespace App\Models;

use App\Enums\CashSessionStatus;
use Database\Factories\CashSessionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A ticket agent's cash register, from opening (float) to closing
 * (counted cash, difference, justification). See App\Actions\Cash.
 *
 * @property int $id
 * @property int $user_id
 * @property CashSessionStatus $status
 * @property int $opening_float
 * @property Carbon $opened_at
 * @property Carbon|null $closed_at
 * @property int|null $expected_amount
 * @property int|null $counted_amount
 * @property int|null $difference
 * @property string|null $justification
 */
class CashSession extends Model
{
    /** @use HasFactory<CashSessionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'opening_float',
        'opened_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CashSessionStatus::class,
            'opening_float' => 'integer',
            'expected_amount' => 'integer',
            'counted_amount' => 'integer',
            'difference' => 'integer',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
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
     * Cash sales of this register (cancelled ones included).
     *
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @param  Builder<CashSession>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', CashSessionStatus::Open);
    }

    public function isOpen(): bool
    {
        return $this->status === CashSessionStatus::Open;
    }
}
