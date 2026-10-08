<?php

namespace Database\Factories;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => '+22507'.fake()->unique()->numerify('########'),
            'email' => null,
            'email_verified_at' => null,
            'password' => static::$password ??= Hash::make('password'),
            'status' => UserStatus::Active,
            'must_change_password' => false,
            'failed_login_attempts' => 0,
            'privacy_accepted_at' => now(),
            'remember_token' => Str::random(10),
        ];
    }

    public function withEmail(bool $verified = true): static
    {
        return $this->state(fn () => [
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => $verified ? now() : null,
        ]);
    }

    public function disabled(): static
    {
        return $this->state(fn () => ['status' => UserStatus::Disabled]);
    }

    public function locked(): static
    {
        return $this->state(fn () => [
            'failed_login_attempts' => (int) config('transport.security.max_login_attempts'),
            'locked_until' => now()->addMinutes((int) config('transport.security.lockout_minutes')),
        ]);
    }

    public function mustChangePassword(): static
    {
        return $this->state(fn () => ['must_change_password' => true]);
    }

    /**
     * Assign a role after creation (roles must exist: RolesAndPermissionsSeeder).
     */
    public function role(RoleName $role): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole(Role::findByName($role->value, 'web')));
    }
}
