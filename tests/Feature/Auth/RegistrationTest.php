<?php

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function registration(array $overrides = []): array
{
    return [
        'first_name' => 'Awa',
        'last_name' => 'Koné',
        'phone' => '07 12 34 56 78',
        'email' => '',
        'password' => 'mon bus pour bassam',
        'password_confirmation' => 'mon bus pour bassam',
        'privacy' => '1',
        ...$overrides,
    ];
}

it('shows the sign-up screen', function () {
    $this->get(route('register'))->assertOk()->assertSee('Créer mon compte');
});

it('creates a customer account, normalizes the phone and logs in', function () {
    Notification::fake();

    $this->post(route('register'), registration())->assertRedirect(route('espace.home'));

    $user = User::query()->where('phone', '+2250712345678')->firstOrFail();

    expect($user)
        ->first_name->toBe('Awa')
        ->email->toBeNull()
        ->status->toBe(UserStatus::Active)
        ->privacy_accepted_at->not->toBeNull()
        ->and($user->hasRole(RoleName::Client->value))->toBeTrue()
        ->and($user->getRoleNames()->all())->toBe(['client']);

    $this->assertAuthenticatedAs($user);
    Notification::assertNothingSent();
});

it('sends a confirmation link when an e-mail is given', function () {
    Notification::fake();

    $this->post(route('register'), registration(['email' => 'Awa@Exemple.ci']));

    $user = User::query()->where('email', 'awa@exemple.ci')->firstOrFail();
    expect($user->hasVerifiedEmail())->toBeFalse();
    Notification::assertSentTo($user, VerifyEmail::class);
});

it('ignores any attempt to choose a role or a status', function () {
    $this->post(route('register'), registration([
        'role' => 'super-admin',
        'roles' => ['super-admin'],
        'status' => 'disabled',
        'must_change_password' => '1',
        'email_verified_at' => now()->toDateTimeString(),
    ]));

    $user = User::query()->where('phone', '+2250712345678')->firstOrFail();

    expect($user->getRoleNames()->all())->toBe(['client'])
        ->and($user->isActive())->toBeTrue()
        ->and($user->must_change_password)->toBeFalse()
        ->and($user->email_verified_at)->toBeNull();
});

it('refuses an invalid or already used phone number', function () {
    User::factory()->create(['phone' => '+2250712345678']);

    $this->post(route('register'), registration())->assertSessionHasErrors('phone');
    $this->post(route('register'), registration(['phone' => '0812345678']))
        ->assertSessionHasErrors(['phone' => 'Numéro invalide : 10 chiffres commençant par 01, 05, 07, 21, 25 ou 27.']);

    $this->assertGuest();
});

it('requires a 12-character password typed twice', function () {
    $this->post(route('register'), registration(['password' => 'court', 'password_confirmation' => 'court']))
        ->assertSessionHasErrors('password');

    $this->post(route('register'), registration(['password_confirmation' => 'autre chose 123']))
        ->assertSessionHasErrors(['password' => 'Les deux mots de passe ne sont pas identiques.']);
});

it('requires consent to personal data processing', function () {
    $this->post(route('register'), registration(['privacy' => null]))->assertSessionHasErrors('privacy');

    expect(User::query()->count())->toBe(0);
});
