<?php

namespace Database\Factories;

use App\Enums\PassChannel;
use App\Enums\PassStatus;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'user_id' => User::factory(),
            'zone_id' => Zone::factory(),
            'price_paid' => 1500,
            'valid_on' => today(),
            'rides_allowed' => 2,
            'rides_used' => 0,
            'channel' => PassChannel::Online,
            'status' => PassStatus::Active,
        ];
    }
}
