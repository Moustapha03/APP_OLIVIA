<?php

namespace App\Models;

use App\Enums\CashSessionStatus;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Support\PhoneNumber;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Spatie\Permission\Traits\HasRoles;

/**
 * Everyone logs in with phone number + password (no SMS code, no 2FA).
 * The e-mail address is optional: once verified, it can receive a
 * password reset link. Security-sensitive columns (status, lockout,
 * must_change_password…) are deliberately NOT mass assignable.
 *
 * @property int $id
 * @property string $first_name
 * @property string $last_name
 * @property string $phone
 * @property string|null $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property UserStatus $status
 * @property bool $must_change_password
 * @property int $failed_login_attempts
 * @property Carbon|null $locked_until
 * @property Carbon|null $last_login_at
 * @property string|null $last_login_ip
 * @property Carbon|null $privacy_accepted_at
 * @property-read string $name
 * @property-read string $formatted_phone
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /** Permissions that open a staff space (no "remember me" for them). */
    public const STAFF_SPACES = ['space.admin', 'space.guichet', 'space.controle'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'phone',
        'email',
        'password',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'must_change_password' => 'boolean',
            'failed_login_attempts' => 'integer',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
            'privacy_accepted_at' => 'datetime',
        ];
    }

    /**
     * Full name, e.g. "Awa Koné".
     *
     * @return Attribute<string, never>
     */
    protected function name(): Attribute
    {
        return Attribute::get(fn (): string => trim("{$this->first_name} {$this->last_name}"));
    }

    /**
     * Phone for display: "07 00 00 00 00".
     *
     * @return Attribute<string, never>
     */
    protected function formattedPhone(): Attribute
    {
        return Attribute::get(fn (): string => PhoneNumber::format($this->phone));
    }

    /**
     * @return HasMany<CashSession, $this>
     */
    public function cashSessions(): HasMany
    {
        return $this->hasMany(CashSession::class);
    }

    /**
     * @return HasOne<CashSession, $this>
     */
    public function openCashSession(): HasOne
    {
        return $this->hasOne(CashSession::class)->where('status', CashSessionStatus::Open)->latestOfMany('opened_at');
    }

    /**
     * @return HasMany<Ticket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Subscribers (abonnés) this customer account manages.
     *
     * @return HasMany<Subscriber, $this>
     */
    public function subscribers(): HasMany
    {
        return $this->hasMany(Subscriber::class);
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    /**
     * Only used to protect super-admin accounts (DECISIONS.md D22).
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole(RoleName::SuperAdmin->value);
    }

    public function isStaff(): bool
    {
        return $this->canAny(self::STAFF_SPACES);
    }

    public function hasVerifiedEmail(): bool
    {
        return $this->email !== null && $this->email_verified_at !== null;
    }

    /**
     * No e-mail address = nothing to verify.
     */
    public function sendEmailVerificationNotification(): void
    {
        if ($this->email !== null) {
            parent::sendEmailVerificationNotification();
        }
    }
}
