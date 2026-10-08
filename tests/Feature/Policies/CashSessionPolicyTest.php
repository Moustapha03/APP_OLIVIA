<?php

use App\Enums\RoleName;
use App\Models\CashSession;
use Illuminate\Support\Facades\Gate;

it('lets an agent see and close only their own cash register (IDOR)', function () {
    $alice = userWithRole(RoleName::Guichetier);
    $bob = userWithRole(RoleName::Guichetier);
    $aliceSession = CashSession::factory()->for($alice)->create();

    expect(Gate::forUser($alice)->allows('view', $aliceSession))->toBeTrue()
        ->and(Gate::forUser($alice)->allows('close', $aliceSession))->toBeTrue()
        ->and(Gate::forUser($bob)->denies('view', $aliceSession))->toBeTrue()
        ->and(Gate::forUser($bob)->denies('close', $aliceSession))->toBeTrue()
        ->and(Gate::forUser($bob)->denies('viewAny', CashSession::class))->toBeTrue();
});

it('shows the agent their own register, never another one', function () {
    $alice = userWithRole(RoleName::Guichetier);
    $bob = userWithRole(RoleName::Guichetier);
    CashSession::factory()->for($alice)->create(['opening_float' => 35000]);

    $this->actingAs($bob)->get(route('guichet.cash.show'))->assertOk()->assertSee('Ouvrir ma caisse')->assertDontSee("35\u{00A0}000");
    $this->actingAs($alice)->get(route('guichet.cash.show'))->assertOk()->assertSee("35\u{00A0}000\u{00A0}FCFA");
});

it('cannot close a register twice', function () {
    $agent = userWithRole(RoleName::Guichetier);
    $closed = CashSession::factory()->closed()->for($agent)->create();

    expect(Gate::forUser($agent)->denies('close', $closed))->toBeTrue();
});

it('lets accountants see every register and reconcile closed ones', function () {
    $accountant = userWithRole(RoleName::Comptable);
    $open = CashSession::factory()->create();
    $closed = CashSession::factory()->closed()->create();

    expect(Gate::forUser($accountant)->allows('viewAny', CashSession::class))->toBeTrue()
        ->and(Gate::forUser($accountant)->allows('view', $open))->toBeTrue()
        ->and(Gate::forUser($accountant)->allows('reconcile', $closed))->toBeTrue()
        ->and(Gate::forUser($accountant)->denies('reconcile', $open))->toBeTrue()
        ->and(Gate::forUser($accountant)->denies('close', $open))->toBeTrue();
});

it('denies cash registers to roles without cash permissions', function (RoleName $role) {
    $session = CashSession::factory()->create();

    expect(Gate::forUser(userWithRole($role))->denies('view', $session))->toBeTrue();
})->with([RoleName::Direction, RoleName::Controleur, RoleName::Client]);
