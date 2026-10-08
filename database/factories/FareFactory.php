<?php

namespace Database\Factories;

use App\Enums\FareType;
use App\Models\Fare;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Fare>
 */
class FareFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'zone_id' => Zone::factory(),
            'type' => FareType::Daily,
            'amount' => 1500,
            'effective_from' => today()->subMonth(),
            'effective_to' => null,
        ];
    }

    public function type(FareType $type, int $amount): static
    {
        return $this->state(fn () => ['type' => $type, 'amount' => $amount]);
    }

    public function monthly(int $amount = 50000): static
    {
        return $this->type(FareType::Monthly, $amount);
    }
}
