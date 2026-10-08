<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * One account per role, to try the access rules locally.
 * Password for all: "demo-password-2026". Local/testing only.
 * (The full demo data set comes with DemoSeeder in step 10.)
 */
class DemoUsersSeeder extends Seeder
{
    public const PASSWORD = 'demo-password-2026';

    /** @var array<string, array{string, string, string}> role => [phone, first name, last name] */
    private const ACCOUNTS = [
        'gestionnaire' => ['+2250700000001', 'Gaston', 'Gestionnaire'],
        'direction' => ['+2250700000002', 'Diane', 'Direction'],
        'comptable' => ['+2250700000003', 'Claude', 'Comptable'],
        'guichetier' => ['+2250700000004', 'Grâce', 'Guichet'],
        'controleur' => ['+2250700000005', 'Konan', 'Contrôleur'],
        'client' => ['+2250700000006', 'Awa', 'Koné'],
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command->warn('DemoUsersSeeder skipped: only allowed in local/testing.');

            return;
        }

        foreach (self::ACCOUNTS as $role => [$phone, $firstName, $lastName]) {
            $user = User::query()->firstOrNew(['phone' => $phone]);
            $user->forceFill([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'password' => self::PASSWORD,
                'status' => UserStatus::Active,
                'privacy_accepted_at' => now(),
            ])->save();

            $user->syncRoles([RoleName::from($role)->value]);
        }

        $this->command->info('Demo accounts: 07 00 00 00 01 to 06, password "'.self::PASSWORD.'"');
    }
}
