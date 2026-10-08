<?php

namespace Database\Seeders;

use App\Models\Line;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    // No WithoutModelEvents: spatie/laravel-permission flushes its cache on
    // model events; muted, it keeps a stale empty list during seeding.

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            ReferenceDataSeeder::class,
            ExpenseCategorySeeder::class,
            AdminUserSeeder::class,
            DemoUsersSeeder::class,
        ]);

        // A few buses to try the back office (local only).
        if (app()->environment('local') && Vehicle::query()->doesntExist()) {
            $line = Line::query()->where('code', 'ABJ-BNA')->first();

            foreach ([['Bus 12', '1234 AB 01', 70], ['Bus 15', '5678 CD 01', 70], ['Minibus 3', '9012 EF 01', 30]] as [$name, $registration, $capacity]) {
                Vehicle::query()->create([
                    'name' => $name,
                    'registration' => $registration,
                    'capacity' => $capacity,
                    'line_id' => $line?->id,
                    'is_active' => true,
                ]);
            }
        }
    }
}
