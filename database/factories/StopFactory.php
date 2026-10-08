<?php

namespace Database\Factories;

use App\Models\Stop;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Stop>
 */
class StopFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'zone_id' => Zone::factory(),
            'name' => fake()->unique()->streetName(),
            'is_active' => true,
        ];
    }
}
