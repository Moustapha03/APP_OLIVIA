<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * An agency expense. Every change is logged with old and new values;
 * "Supprimer" archives it (soft delete), it no longer counts in totals.
 *
 * @property int $id
 * @property int $expense_category_id
 * @property Carbon $spent_on
 * @property int $amount
 * @property string $label
 * @property PaymentMethod $payment_method
 * @property string|null $reference
 * @property int|null $vehicle_id
 * @property string|null $attachment_path
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property-read ExpenseCategory $category
 * @property-read Vehicle|null $vehicle
 * @property-read User|null $author
 */
class Expense extends Model
{
    /** @use HasFactory<ExpenseFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'expense_category_id',
        'spent_on',
        'amount',
        'label',
        'payment_method',
        'reference',
        'vehicle_id',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'spent_on' => 'date',
            'amount' => 'integer',
            'payment_method' => PaymentMethod::class,
        ];
    }

    /**
     * @return BelongsTo<ExpenseCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function hasAttachment(): bool
    {
        return $this->attachment_path !== null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('expenses')
            ->logOnly(['expense_category_id', 'spent_on', 'amount', 'label', 'payment_method', 'reference', 'vehicle_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
