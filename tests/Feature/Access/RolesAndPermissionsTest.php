<?php

use App\Enums\RoleName;
use App\Support\PermissionCatalog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('seeds every permission and every role', function () {
    expect(Permission::query()->pluck('name')->sort()->values()->all())
        ->toBe(collect(PermissionCatalog::all())->sort()->values()->all())
        ->and(Role::query()->pluck('name')->sort()->values()->all())
        ->toBe(collect(RoleName::cases())->map->value->sort()->values()->all());
});

it('is idempotent', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(Permission::query()->count())->toBe(count(PermissionCatalog::all()))
        ->and(Role::query()->count())->toBe(count(RoleName::cases()));
});

it('names every permission "resource.action"', function () {
    foreach (PermissionCatalog::all() as $permission) {
        expect($permission)->toMatch('/^[a-z]+(-[a-z]+)*\.[a-z]+(-[a-z]+)*$/');
    }
});

it('only grants permissions that exist', function (RoleName $role) {
    expect(array_diff(PermissionCatalog::forRole($role), PermissionCatalog::all()))->toBe([]);
})->with(RoleName::cases());

it('keeps "direction" read only', function () {
    $writes = array_filter(PermissionCatalog::forRole(RoleName::Direction), fn (string $p) => preg_match('/\.(manage|sell|cancel|refund|open|close|reconcile|create)$/', $p));

    expect($writes)->toBe([]);
});

it('requires a supervisor to cancel a sale: ticket agents cannot', function () {
    expect(PermissionCatalog::forRole(RoleName::Guichetier))->not->toContain('tickets.cancel')
        ->and(PermissionCatalog::forRole(RoleName::Gestionnaire))->toContain('tickets.cancel');
});

it('gives customers nothing but their space and online purchase', function () {
    expect(PermissionCatalog::forRole(RoleName::Client))->toBe(['space.espace', 'passes.purchase']);
});
