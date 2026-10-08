<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Local development admin account.
 *
 * Login: 07 00 00 00 00 (or admin@gmail.com) / 1234567890
 *
 * The password is weak on purpose (developer convenience), so this seeder
 * refuses to run outside the "local" and "testing" environments.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command->warn('AdminUserSeeder skipped: only allowed in local/testing.');

            return;
        }

        $user = User::query()->firstOrNew(['email' => 'admin@gmail.com']);

        $user->forceFill([
            'first_name' => 'Administrateur',
            'last_name' => 'Principal',
            'phone' => '+2250700000000',
            'password' => '1234567890',
            'email_verified_at' => now(),
            'status' => UserStatus::Active,
            'privacy_accepted_at' => now(),
        ])->save();

        $user->syncRoles([RoleName::SuperAdmin->value]);

        $this->command->info('Admin account ready: 07 00 00 00 00 or admin@gmail.com');
    }
}
