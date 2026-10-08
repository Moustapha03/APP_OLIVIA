<?php

use App\Enums\RoleName;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

it('shows the login screen', function () {
    $this->get(route('login'))->assertOk()->assertSee('Numéro de téléphone');
});

it('logs in with the phone number typed in any common format', function (string $typed) {
    $user = userWithRole(RoleName::Client, ['phone' => '+2250700000006']);

    $this->post(route('login'), ['login' => $typed, 'password' => 'password'])
        ->assertRedirect(route('espace.home'));

    $this->assertAuthenticatedAs($user);
})->with(['0700000006', '07 00 00 00 06', '07.00.00.00.06', '+225 07 00 00 00 06', '00225 0700000006']);

it('also accepts the e-mail address', function () {
    $user = userWithRole(RoleName::Gestionnaire, ['email' => 'chef@exemple.ci']);

    $this->post(route('login'), ['login' => 'Chef@Exemple.ci', 'password' => 'password'])
        ->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticatedAs($user);
});

it('regenerates the session on login', function () {
    userWithRole(RoleName::Client, ['phone' => '+2250700000006']);
    $this->startSession();
    $before = session()->getId();

    $this->post(route('login'), ['login' => '0700000006', 'password' => 'password']);

    expect(session()->getId())->not->toBe($before);
});

it('refuses a wrong password and counts the attempt', function () {
    $user = userWithRole(RoleName::Client, ['phone' => '+2250700000006']);

    $this->post(route('login'), ['login' => '0700000006', 'password' => 'wrong'])
        ->assertSessionHasErrors(['login' => 'Mot de passe incorrect. Il vous reste 4 essais.']);

    $this->assertGuest();
    expect($user->fresh()->failed_login_attempts)->toBe(1);
});

it('gives the same answer for an unknown number', function () {
    $this->post(route('login'), ['login' => '0500000000', 'password' => 'whatever'])
        ->assertSessionHasErrors(['login' => 'Numéro ou mot de passe incorrect.']);

    $this->assertGuest();
});

it('locks the account after 5 wrong passwords, even with the right one afterwards', function () {
    $user = userWithRole(RoleName::Client, ['phone' => '+2250700000006']);

    foreach (range(1, 4) as $attempt) {
        $this->post(route('login'), ['login' => '0700000006', 'password' => 'wrong']);
    }

    $this->post(route('login'), ['login' => '0700000006', 'password' => 'wrong'])
        ->assertSessionHasErrors('login');

    expect($user->fresh()->isLocked())->toBeTrue()
        ->and(session('errors')->first('login'))->toContain('bloqué pendant 15 min');

    // Right password, still locked (simulate a new minute: route limiter reset).
    $this->travel(2)->minutes();
    $this->post(route('login'), ['login' => '0700000006', 'password' => 'password'])
        ->assertSessionHasErrors('login');
    $this->assertGuest();

    expect(Activity::query()->where('event', 'lockout')->where('subject_id', $user->id)->exists())->toBeTrue();
});

it('unlocks after the lockout delay and resets the counter', function () {
    $user = userWithRole(RoleName::Client, ['phone' => '+2250700000006']);
    $user->forceFill(['failed_login_attempts' => 5, 'locked_until' => now()->addMinutes(15)])->save();

    $this->travel(16)->minutes();

    $this->post(route('login'), ['login' => '0700000006', 'password' => 'password'])
        ->assertRedirect(route('espace.home'));

    expect($user->fresh())
        ->failed_login_attempts->toBe(0)
        ->locked_until->toBeNull()
        ->last_login_at->not->toBeNull();
});

it('refuses a disabled account, but only once the password is right', function () {
    User::factory()->disabled()->role(RoleName::Client)->create(['phone' => '+2250700000006']);

    $this->post(route('login'), ['login' => '0700000006', 'password' => 'password'])
        ->assertSessionHasErrors(['login' => 'Votre compte a été désactivé. Contactez la compagnie de transport.']);

    $this->post(route('login'), ['login' => '0700000006', 'password' => 'wrong'])
        ->assertSessionHasErrors(['login' => 'Mot de passe incorrect. Il vous reste 4 essais.']);

    $this->assertGuest();
});

it('keeps customers logged in when asked, never staff', function (RoleName $role, bool $remembered) {
    userWithRole($role, ['phone' => '+2250700000009']);

    $response = $this->post(route('login'), ['login' => '0700000009', 'password' => 'password', 'remember' => '1']);

    $cookie = collect($response->headers->getCookies())->first(fn ($c) => str_starts_with($c->getName(), 'remember_web_'));
    expect($cookie !== null)->toBe($remembered);
})->with([
    [RoleName::Client, true],
    [RoleName::Guichetier, false],
    [RoleName::Controleur, false],
    [RoleName::Gestionnaire, false],
    [RoleName::SuperAdmin, false],
]);

it('writes staff logins to the audit log', function () {
    $agent = userWithRole(RoleName::Comptable, ['phone' => '+2250700000003']);

    $this->post(route('login'), ['login' => '0700000003', 'password' => 'password']);

    expect(Activity::query()->where('event', 'login')->where('causer_id', $agent->id)->exists())->toBeTrue();
});

it('refuses an account that has no space', function () {
    User::factory()->create(['phone' => '+2250700000008']);

    $this->post(route('login'), ['login' => '0700000008', 'password' => 'password'])
        ->assertRedirect(route('login'))
        ->assertSessionHas('error');

    $this->assertGuest();
});

it('throttles login attempts: 5 per minute', function () {
    foreach (range(1, 5) as $attempt) {
        $this->post(route('login'), ['login' => '0500000000', 'password' => 'x'])->assertStatus(302);
    }

    $this->post(route('login'), ['login' => '0500000000', 'password' => 'x'])->assertStatus(429);
});

it('logs out with a POST only', function () {
    $user = userWithRole(RoleName::Client);

    $this->actingAs($user)->get('/deconnexion')->assertStatus(405);
    $this->actingAs($user)->post(route('logout'))->assertRedirect(route('home'));
    $this->assertGuest();
});

it('sends logged-in users away from the login page', function () {
    $this->actingAs(userWithRole(RoleName::Controleur))
        ->get(route('login'))
        ->assertRedirect(route('controle.home'));
});
