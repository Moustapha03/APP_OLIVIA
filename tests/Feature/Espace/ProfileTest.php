<?php

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

it('shows the profile page', function () {
    $this->actingAs(userWithRole(RoleName::Client, ['phone' => '+2250712345678']))
        ->get(route('espace.profile.edit'))
        ->assertOk()
        ->assertSee('07 12 34 56 78');
});

it('updates the name without touching the phone number', function () {
    $user = userWithRole(RoleName::Client, ['phone' => '+2250712345678']);

    $this->actingAs($user)->patch(route('espace.profile.update'), [
        'first_name' => 'Awa',
        'last_name' => 'Traoré',
        'phone' => '+2250500000000',
    ])->assertRedirect(route('espace.profile.edit'))->assertSessionHas('success');

    expect($user->fresh())
        ->last_name->toBe('Traoré')
        ->phone->toBe('+2250712345678');
});

it('asks to confirm a new e-mail address again', function () {
    Notification::fake();
    $user = User::factory()->withEmail(verified: true)->role(RoleName::Client)->create();

    $this->actingAs($user)->patch(route('espace.profile.update'), [
        'first_name' => $user->first_name,
        'last_name' => $user->last_name,
        'email' => 'nouvelle@exemple.ci',
    ]);

    $user->refresh();
    expect($user->email)->toBe('nouvelle@exemple.ci')->and($user->hasVerifiedEmail())->toBeFalse();
    Notification::assertSentTo($user, VerifyEmail::class);
});

it('keeps the verification when the e-mail does not change', function () {
    $user = User::factory()->withEmail(verified: true)->role(RoleName::Client)->create();

    $this->actingAs($user)->patch(route('espace.profile.update'), [
        'first_name' => 'Nouveau',
        'last_name' => $user->last_name,
        'email' => $user->email,
    ]);

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('deletes the account with the password', function () {
    $user = userWithRole(RoleName::Client);

    $this->actingAs($user)->delete(route('espace.profile.destroy'), ['password' => 'password'])
        ->assertRedirect(route('home'));

    $this->assertGuest();
    expect($user->fresh())->toBeNull();
});

it('keeps the account when the password is wrong', function () {
    $user = userWithRole(RoleName::Client);

    $this->actingAs($user)->from(route('espace.profile.edit'))
        ->delete(route('espace.profile.destroy'), ['password' => 'wrong'])
        ->assertSessionHasErrorsIn('accountDeletion', 'password')
        ->assertRedirect(route('espace.profile.edit'));

    expect($user->fresh())->not->toBeNull();
});
