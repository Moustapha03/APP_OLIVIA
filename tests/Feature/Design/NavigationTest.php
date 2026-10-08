<?php

use App\Enums\RoleName;
use App\Models\User;
use App\Support\Navigation;

/**
 * @param  array<string, list<array{label: string}>>  $menu
 * @return list<string>
 */
function menuLabels(array $menu): array
{
    return collect($menu)->flatten(1)->pluck('label')->all();
}

it('hides items whose route does not exist yet', function () {
    // controle.infractions.create arrives in step 7.
    $labels = menuLabels(Navigation::for('controle', userWithRole(RoleName::Controleur)));

    expect($labels)->toContain('Scanner')->not->toContain('Infraction');
});

it('shows nothing to a user without the space permission', function () {
    expect(Navigation::for('espace', User::factory()->create()))->toBe([]);
});

it('shows every item in preview mode with placeholder links', function () {
    $menu = Navigation::for('controle', null, preview: true);

    // Scanner and history exist, Infraction arrives later.
    expect($menu[''])->toHaveCount(4)
        ->and($menu[''][0]['href'])->toBe(route('controle.scan'))
        ->and($menu[''][1]['href'])->toBe(route('controle.scans.index'))
        ->and($menu[''][3]['href'])->toBe('#');
});

it('shows an item only to users holding its permission', function () {
    $allowed = User::factory()->create();
    $allowed->givePermissionTo('vehicles.view');
    $denied = User::factory()->create();

    expect(menuLabels(Navigation::for('admin', $allowed)))->toContain('Véhicules')
        ->and(menuLabels(Navigation::for('admin', $denied)))->not->toContain('Véhicules')
        ->and(Navigation::for('admin', null))->toBe([]);
});

it('shows the style guide link to the super-admin only', function () {
    expect(menuLabels(Navigation::for('admin', superAdmin())))->toContain('Charte graphique')
        ->and(menuLabels(Navigation::for('admin', User::factory()->create())))->not->toContain('Charte graphique');
});
