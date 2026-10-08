<?php

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->agent = userWithRole(RoleName::Guichetier);
});

it('creates a customer at the desk with consent and a temporary password', function () {
    $this->actingAs($this->agent)->post(route('guichet.customers.store'), [
        'first_name' => 'Awa', 'last_name' => 'Koné', 'phone' => '07 12 34 56 78', 'privacy' => '1',
    ])->assertRedirect();

    $customer = User::query()->where('phone', '+2250712345678')->sole();
    $temporary = session('temporary_password');

    expect($customer->getRoleNames()->all())->toBe(['client'])
        ->and($customer->privacy_accepted_at)->not->toBeNull()
        ->and($customer->must_change_password)->toBeTrue()
        ->and(Hash::check($temporary['password'], $customer->password))->toBeTrue();
});

it('requires the customer consent and a free phone number', function () {
    userWithRole(RoleName::Client, ['phone' => '+2250712345678']);

    $this->actingAs($this->agent)->post(route('guichet.customers.store'), ['first_name' => 'A', 'last_name' => 'B', 'phone' => '0712345678'])
        ->assertSessionHasErrors(['phone' => 'Ce numéro a déjà un compte : recherchez-le.', 'privacy']);
});

it('finds customers, never staff', function () {
    userWithRole(RoleName::Client, ['first_name' => 'Awa', 'last_name' => 'Koné', 'phone' => '+2250712345678']);
    userWithRole(RoleName::Gestionnaire, ['first_name' => 'Gaston', 'last_name' => 'Koné', 'phone' => '+2250712345679']);

    $this->actingAs($this->agent)->get(route('guichet.customers.index', ['q' => 'Koné']))
        ->assertOk()->assertSee('Awa Koné')->assertDontSee('Gaston');
});

it('gives a customer a temporary password, never to staff', function () {
    $customer = User::factory()->locked()->role(RoleName::Client)->create();
    $controller = userWithRole(RoleName::Controleur);

    $this->actingAs($this->agent)->post(route('guichet.customers.password.reset', $customer))->assertRedirect();
    expect($customer->fresh()->isLocked())->toBeFalse()->and($customer->fresh()->must_change_password)->toBeTrue();

    $this->actingAs($this->agent)->post(route('guichet.customers.password.reset', $controller))->assertForbidden();
});
