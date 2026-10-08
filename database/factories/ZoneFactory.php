<?php

namespace Database\Factories;

use App\Models\Line;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Zone>
 */
class ZoneFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'line_id' => Line::factory(),
            'name' => 'Zone '.fake()->unique()->city(),
            'position' => fn (array $attributes) => (int) Zone::query()->where('line_id', $attributes['line_id'])->max('position') + 1,
            'is_active' => true,
        ];
    }
}
