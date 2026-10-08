<?php

namespace App\Models;

use App\Enums\FareType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * What the customer pays for (purpose + stop + amount frozen at purchase)
 * and how it went. The pass is issued from it by ConfirmPayment only.
 * Every column is written by Actions, never mass assigned from a form.
 *
 * @property int $id
 * @property string $reference
 * @property int|null $user_id
 * @property FareType $purpose
 * @property string|null $receipt_number
 * @property int|null $subscriber_id
 * @property int $zone_id
 * @property int|null $stop_id
 * @property Carbon|null $period_month
 * @property int $list_amount
 * @property string|null $discount_reason
 * @property int|null $discounted_by
 * @property string|null $external_reference
 * @property int|null $fare_id
 * @property int $amount
 * @property string $currency
 * @property string $gateway
 * @property PaymentStatus $status
 * @property string|null $gateway_transaction_id
 * @property PaymentMethod|null $payment_method
 * @property string|null $failure_reason
 * @property array<string, mixed>|null $payload
 * @property string|null $payable_type
 * @property int|null $payable_id
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $failed_at
 * @property int|null $cash_session_id
 * @property int|null $sold_by
 * @property Carbon|null $cancelled_at
 * @property int|null $cancelled_by
 * @property string|null $cancellation_reason
 * @property Carbon $created_at
 * @property-read User|null $user
 * @property-read Zone $zone
 * @property-read Stop|null $stop
 * @property-read Subscriber|null $subscriber
 * @property-read Ticket|Subscription|null $payable
 * @property-read User|null $seller
 * @property-read CashSession|null $cashSession
 */
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
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
            'purpose' => FareType::class,
            'status' => PaymentStatus::class,
            'amount' => 'integer',
            'list_amount' => 'integer',
            'period_month' => 'date',
            'payment_method' => PaymentMethod::class,
            'payload' => 'array',
            'confirmed_at' => 'datetime',
            'failed_at' => 'datetime',
            'cancelled_at' => 'datetime',
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
     * @return BelongsTo<Subscriber, $this>
     */
    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Subscriber::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Stop, $this>
     */
    public function stop(): BelongsTo
    {
        return $this->belongsTo(Stop::class);
    }

    /**
     * @return BelongsTo<Fare, $this>
     */
    public function fare(): BelongsTo
    {
        return $this->belongsTo(Fare::class);
    }

    /**
     * The pass issued once paid.
     *
     * @return MorphTo<Model, $this>
     */
    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sold_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * @return BelongsTo<CashSession, $this>
     */
    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    public function isPending(): bool
    {
        return $this->status === PaymentStatus::Pending;
    }

    public function isDiscounted(): bool
    {
        return $this->amount !== $this->list_amount;
    }

    public function isSucceeded(): bool
    {
        return $this->status === PaymentStatus::Succeeded;
    }
}
