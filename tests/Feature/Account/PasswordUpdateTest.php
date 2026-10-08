<?php

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\Models\Activity;

it('changes the password with the current one', function () {
    $user = userWithRole(RoleName::Guichetier);

    $this->actingAs($user)->put(route('account.password.update'), [
        'current_password' => 'password',
        'password' => 'une phrase bien longue',
        'password_confirmation' => 'une phrase bien longue',
    ])->assertRedirect(route('guichet.home'))->assertSessionHas('success');

    expect(Hash::check('une phrase bien longue', $user->fresh()->password))->toBeTrue();
});

it('refuses a wrong current password', function () {
    $user = userWithRole(RoleName::Client);

    $this->actingAs($user)->put(route('account.password.update'), [
        'current_password' => 'wrong',
        'password' => 'une phrase bien longue',
        'password_confirmation' => 'une phrase bien longue',
    ])->assertSessionHasErrors(['current_password' => 'Le mot de passe actuel est incorrect.']);

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

it('writes the change to the audit log without any password', function () {
    $user = userWithRole(RoleName::Client);

    $this->actingAs($user)->put(route('account.password.update'), [
        'current_password' => 'password',
        'password' => 'une phrase bien longue',
        'password_confirmation' => 'une phrase bien longue',
    ]);

    $entry = Activity::query()->where('log_name', 'audit')->where('causer_id', $user->id)->latest('id')->firstOrFail();

    expect($entry->description)->toBe('Changement de mot de passe')
        ->and(json_encode($entry->properties))->not->toContain('une phrase bien longue')
        ->and(json_encode($entry->properties))->not->toContain('"password"');
});

it('forces a user with a temporary password to change it first', function () {
    $user = User::factory()->mustChangePassword()->role(RoleName::Client)->create();

    $this->actingAs($user)->get(route('espace.home'))
        ->assertRedirect(route('account.password.edit'))
        ->assertSessionHas('warning');

    $this->actingAs($user)->get(route('account.password.edit'))->assertOk()->assertSee('Mot de passe provisoire');

    $this->actingAs($user)->put(route('account.password.update'), [
        'current_password' => 'password',
        'password' => 'une phrase bien longue',
        'password_confirmation' => 'une phrase bien longue',
    ])->assertRedirect(route('espace.home'));

    expect($user->fresh()->must_change_password)->toBeFalse();
    $this->actingAs($user->fresh())->get(route('espace.home'))->assertOk();
});

it('still lets a user with a temporary password log out', function () {
    $user = User::factory()->mustChangePassword()->role(RoleName::Client)->create();

    $this->actingAs($user)->post(route('logout'))->assertRedirect(route('home'));
    $this->assertGuest();
});
