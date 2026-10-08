<?php

namespace Database\Factories;

use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'registration' => strtoupper(fake()->unique()->bothify('#### ?? ##')),
            'name' => 'Bus '.fake()->unique()->numberBetween(1, 999),
            'capacity' => 70,
            'line_id' => null,
            'is_active' => true,
            'notes' => null,
        ];
    }
}
