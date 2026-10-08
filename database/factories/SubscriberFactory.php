<?php

namespace Database\Factories;

use App\Models\Stop;
use App\Models\Subscriber;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscriber>
 */
class SubscriberFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'full_name' => fake()->name(),
            'phone' => '+22507'.fake()->numerify('########'),
            'stop_id' => Stop::factory(),
            'photo_path' => 'photos/test.jpg',
        ];
    }

    public function withoutPhoto(): static
    {
        return $this->state(fn () => ['photo_path' => null]);
    }
}
