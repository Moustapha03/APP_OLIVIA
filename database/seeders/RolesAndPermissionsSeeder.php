<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates every permission and role, and syncs each role's permissions
 * (App\Support\PermissionCatalog). Safe to re-run: idempotent.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionCatalog::all() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // Fresh list before syncing (in case model events are muted).
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (RoleName::cases() as $roleName) {
            Role::findOrCreate($roleName->value, 'web')
                ->syncPermissions(PermissionCatalog::forRole($roleName));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
