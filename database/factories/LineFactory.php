<?php

namespace Database\Factories;

use App\Models\Line;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Line>
 */
class LineFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('L-##??')),
            'name' => fake()->city().' – Angré',
            'destination_name' => 'Angré',
            'description' => null,
            'is_active' => true,
        ];
    }
}
