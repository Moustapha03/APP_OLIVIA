<?php

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

const GENERIC_RESET_MESSAGE = 'Si cette adresse est confirmée sur un compte, un lien vient de lui être envoyé. Pensez à regarder dans les courriers indésirables.';

it('shows the forgotten password screen with the ticket office alternative', function () {
    $this->get(route('password.request'))->assertOk()->assertSee('Passez au guichet');
});

it('sends a reset link to a verified e-mail address', function () {
    Notification::fake();
    $user = User::factory()->withEmail(verified: true)->role(RoleName::Client)->create();

    $this->post(route('password.email'), ['email' => strtoupper($user->email)])
        ->assertSessionHas('success', GENERIC_RESET_MESSAGE);

    Notification::assertSentTo($user, ResetPassword::class);
});

it('sends nothing to an unverified or unknown address, with the same answer', function () {
    Notification::fake();
    $user = User::factory()->withEmail(verified: false)->role(RoleName::Client)->create();

    $this->post(route('password.email'), ['email' => $user->email])->assertSessionHas('success', GENERIC_RESET_MESSAGE);
    $this->post(route('password.email'), ['email' => 'inconnu@exemple.ci'])->assertSessionHas('success', GENERIC_RESET_MESSAGE);

    Notification::assertNothingSent();
});

it('resets the password with a valid token and unlocks the account', function () {
    $user = User::factory()->withEmail()->locked()->mustChangePassword()->role(RoleName::Client)->create();
    $token = Password::createToken($user);

    $this->post(route('password.store'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'nouveau mot de passe sûr',
        'password_confirmation' => 'nouveau mot de passe sûr',
    ])->assertRedirect(route('login'))->assertSessionHas('success');

    $user->refresh();
    expect(Hash::check('nouveau mot de passe sûr', $user->password))->toBeTrue()
        ->and($user->isLocked())->toBeFalse()
        ->and($user->failed_login_attempts)->toBe(0)
        ->and($user->must_change_password)->toBeFalse();
});

it('refuses an invalid token', function () {
    $user = User::factory()->withEmail()->role(RoleName::Client)->create();

    $this->post(route('password.store'), [
        'token' => 'invalid-token',
        'email' => $user->email,
        'password' => 'nouveau mot de passe sûr',
        'password_confirmation' => 'nouveau mot de passe sûr',
    ])->assertSessionHasErrors(['email' => 'Ce lien n’est plus valable. Demandez-en un nouveau.']);

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

it('throttles reset link requests', function () {
    foreach (range(1, 3) as $attempt) {
        $this->post(route('password.email'), ['email' => 'a@exemple.ci'])->assertStatus(302);
    }

    $this->post(route('password.email'), ['email' => 'a@exemple.ci'])->assertStatus(429);
});
