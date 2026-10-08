<?php

namespace Database\Factories;

use App\Enums\FareType;
use App\Enums\PassChannel;
use App\Enums\PassStatus;
use App\Models\Subscriber;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'subscriber_id' => Subscriber::factory(),
            'user_id' => null,
            'zone_id' => fn (array $attributes) => Subscriber::query()->find($attributes['subscriber_id'])?->stop?->zone_id,
            'stop_id' => fn (array $attributes) => Subscriber::query()->find($attributes['subscriber_id'])?->stop_id,
            'formula' => FareType::Monthly,
            'price_paid' => 50000,
            'starts_on' => today()->startOfMonth(),
            'ends_on' => today()->endOfMonth()->startOfDay(),
            'channel' => PassChannel::Counter,
            'status' => PassStatus::Active,
        ];
    }
}
