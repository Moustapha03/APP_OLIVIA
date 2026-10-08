<?php

namespace Database\Factories;

use App\Enums\FareType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'TT'.Str::upper((string) Str::ulid()),
            'user_id' => User::factory(),
            'purpose' => FareType::Daily,
            'zone_id' => Zone::factory(),
            'fare_id' => null,
            'list_amount' => fn (array $attributes) => $attributes['amount'] ?? 1500,
            'amount' => 1500,
            'currency' => 'XOF',
            'gateway' => 'fake',
            'payment_method' => PaymentMethod::Online,
            'status' => PaymentStatus::Pending,
        ];
    }

    public function monthly(int $amount = 50000): static
    {
        return $this->state(fn () => [
            'purpose' => FareType::Monthly,
            'amount' => $amount,
            'list_amount' => $amount,
            'period_month' => today()->startOfMonth(),
        ]);
    }

    public function succeeded(): static
    {
        return $this->state(fn () => ['status' => PaymentStatus::Succeeded, 'confirmed_at' => now()]);
    }
}
