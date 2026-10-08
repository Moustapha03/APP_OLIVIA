<?php

namespace Database\Factories;

use App\Enums\CashSessionStatus;
use App\Models\CashSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashSession>
 */
class CashSessionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => CashSessionStatus::Open,
            'opening_float' => 20000,
            'opened_at' => now(),
        ];
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => CashSessionStatus::Closed,
            'closed_at' => now(),
            'expected_amount' => 20000,
            'counted_amount' => 20000,
            'difference' => 0,
        ]);
    }
}
