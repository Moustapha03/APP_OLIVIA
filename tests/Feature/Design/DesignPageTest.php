<?php

use App\Http\Controllers\Design\DesignController;
use App\Models\User;
use Spatie\Permission\Models\Role;

it('redirects guests to the login page', function () {
    $this->get(route('design.index'))->assertRedirect(route('login'));
});

it('forbids authenticated users who are not super-admin', function (string $role) {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate($role, 'web'));

    $this->actingAs($user)->get(route('design.index'))->assertForbidden();
    $this->actingAs($user)->get(route('design.layout', 'admin'))->assertForbidden();
    $this->actingAs($user)->get(route('design.scan', 'valid'))->assertForbidden();
    $this->actingAs($user)->post(route('design.demo'), ['type' => 'confirm'])->assertForbidden();
})->with(['gestionnaire', 'direction', 'comptable', 'guichetier', 'controleur', 'client']);

it('forbids a user without any role', function () {
    $this->actingAs(User::factory()->create())->get(route('design.index'))->assertForbidden();
});

it('shows the style guide to the super-admin', function () {
    $this->actingAs(superAdmin())
        ->get(route('design.index'))
        ->assertOk()
        ->assertSee('Charte graphique')
        ->assertSee("1\u{00A0}500\u{00A0}FCFA")
        ->assertSee('Résultat de scan');
});

it('renders every layout preview', function (string $space) {
    $this->actingAs(superAdmin())
        ->get(route('design.layout', $space))
        ->assertOk()
        ->assertSee('Aperçu de la charte');
})->with(DesignController::SPACES);

it('renders every scan result full screen', function (string $status, string $label) {
    $this->actingAs(superAdmin())
        ->get(route('design.scan', $status))
        ->assertOk()
        ->assertSee($label);
})->with([
    ['valid', 'VALIDE'],
    ['refused', 'REFUSÉ'],
    ['warning', 'ATTENTION'],
]);

it('returns 404 for an unknown preview', function () {
    $admin = superAdmin();

    $this->actingAs($admin)->get('/design/layouts/unknown')->assertNotFound();
    $this->actingAs($admin)->get('/design/scan/unknown')->assertNotFound();
});

it('validates the demo form with French messages', function () {
    $this->actingAs(superAdmin())
        ->from(route('design.index'))
        ->post(route('design.demo'), ['type' => 'form', 'phone' => '123'])
        ->assertRedirect(route('design.index'))
        ->assertSessionHasErrors([
            'phone' => 'Le numéro doit avoir 10 chiffres et commencer par 01, 05, 07, 25 ou 27.',
            'stop_id' => 'Choisissez votre arrêt.',
        ]);
});

it('flashes a success message when the demo form is valid', function () {
    $this->actingAs(superAdmin())
        ->post(route('design.demo'), ['type' => 'form', 'phone' => '0700000000', 'stop_id' => 2])
        ->assertRedirect()
        ->assertSessionHas('success');
});
