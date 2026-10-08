<?php

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

it('lets a manager manage ordinary accounts', function () {
    $manager = userWithRole(RoleName::Gestionnaire);
    $agent = userWithRole(RoleName::Guichetier);

    expect(Gate::forUser($manager)->allows('viewAny', User::class))->toBeTrue()
        ->and(Gate::forUser($manager)->allows('create', User::class))->toBeTrue()
        ->and(Gate::forUser($manager)->allows('update', $agent))->toBeTrue()
        ->and(Gate::forUser($manager)->allows('delete', $agent))->toBeTrue()
        ->and(Gate::forUser($manager)->allows('assignRole', [$agent, RoleName::Client]))->toBeTrue()
        ->and(Gate::forUser($manager)->allows('assignRole', [$agent, RoleName::Gestionnaire]))->toBeTrue()
        // No escalation: roles holding rights the manager lacks (D143).
        ->and(Gate::forUser($manager)->denies('assignRole', [$agent, RoleName::Controleur]))->toBeTrue()
        ->and(Gate::forUser($manager)->denies('assignRole', [$agent, RoleName::Comptable]))->toBeTrue();
});

it('never lets a manager touch a super-admin', function () {
    $manager = userWithRole(RoleName::Gestionnaire);
    $root = superAdmin();

    expect(Gate::forUser($manager)->denies('update', $root))->toBeTrue()
        ->and(Gate::forUser($manager)->denies('delete', $root))->toBeTrue()
        ->and(Gate::forUser($manager)->denies('assignRole', [$root, RoleName::Client]))->toBeTrue()
        ->and(Gate::forUser($manager)->denies('resetPassword', $root))->toBeTrue();
});

it('never lets a manager grant the super-admin role', function () {
    $manager = userWithRole(RoleName::Gestionnaire);
    $agent = userWithRole(RoleName::Guichetier);

    expect(Gate::forUser($manager)->denies('assignRole', [$agent, RoleName::SuperAdmin]))->toBeTrue();
});

it('never lets a manager change their own roles or delete themselves', function () {
    $manager = userWithRole(RoleName::Gestionnaire);

    expect(Gate::forUser($manager)->denies('assignRole', [$manager, RoleName::Comptable]))->toBeTrue()
        ->and(Gate::forUser($manager)->denies('delete', $manager))->toBeTrue();
});

it('denies account management to every other role', function (RoleName $role) {
    $actor = userWithRole($role);
    $target = userWithRole(RoleName::Client);

    expect(Gate::forUser($actor)->denies('viewAny', User::class))->toBeTrue()
        ->and(Gate::forUser($actor)->denies('create', User::class))->toBeTrue()
        ->and(Gate::forUser($actor)->denies('update', $target))->toBeTrue()
        ->and(Gate::forUser($actor)->denies('delete', $target))->toBeTrue()
        ->and(Gate::forUser($actor)->denies('assignRole', [$target, RoleName::Client]))->toBeTrue();
})->with([RoleName::Direction, RoleName::Comptable, RoleName::Guichetier, RoleName::Controleur, RoleName::Client]);

it('lets a ticket agent reset a customer password, never a staff one', function () {
    $agent = userWithRole(RoleName::Guichetier);

    expect(Gate::forUser($agent)->allows('resetPassword', userWithRole(RoleName::Client)))->toBeTrue()
        ->and(Gate::forUser($agent)->denies('resetPassword', userWithRole(RoleName::Controleur)))->toBeTrue()
        ->and(Gate::forUser($agent)->denies('resetPassword', userWithRole(RoleName::Gestionnaire)))->toBeTrue()
        ->and(Gate::forUser($agent)->denies('resetPassword', $agent))->toBeTrue();
});

it('lets anyone see their own account only', function () {
    $client = userWithRole(RoleName::Client);
    $other = userWithRole(RoleName::Client);

    expect(Gate::forUser($client)->allows('view', $client))->toBeTrue()
        ->and(Gate::forUser($client)->denies('view', $other))->toBeTrue();
});
