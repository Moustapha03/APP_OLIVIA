<?php

namespace App\Models;

use App\Support\PhoneNumber;
use Database\Factories\SubscriberFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A person who rides with a subscription (abonné). Not an account:
 * - the contact phone may be shared (a parent pays for a relative);
 * - user_id links the subscriber to the customer account that manages it
 *   (set by the agency, or when the customer adds them online).
 * The photo is shown to the controller (business rule 3). Deleted after
 * paying = archived (soft delete): receipts keep the name.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $full_name
 * @property string|null $phone
 * @property int|null $stop_id
 * @property string|null $photo_path
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon|null $deleted_at
 * @property-read string $formatted_phone
 * @property-read Stop|null $stop
 * @property-read User|null $user
 */
class Subscriber extends Model
{
    /** @use HasFactory<SubscriberFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'full_name',
        'phone',
        'stop_id',
        'notes',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Usual boarding point.
     *
     * @return BelongsTo<Stop, $this>
     */
    public function stop(): BelongsTo
    {
        return $this->belongsTo(Stop::class);
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * @return Attribute<string, never>
     */
    protected function formattedPhone(): Attribute
    {
        return Attribute::get(fn (): string => PhoneNumber::format($this->phone));
    }

    public function hasPhoto(): bool
    {
        return $this->photo_path !== null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('subscribers')->logOnly(['full_name', 'phone', 'stop_id', 'user_id'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
