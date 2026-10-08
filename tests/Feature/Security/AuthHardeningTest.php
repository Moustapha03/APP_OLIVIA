<?php

use App\Actions\Sales\CancelCounterSale;
use App\Actions\Users\ResetUserPassword;
use App\Enums\RoleName;
use App\Models\Payment;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Middleware\TrustHosts;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\Exception\SuspiciousOperationException;

/**
 * Session data of another device of $user, logged in when the password
 * hash was $passwordHash (what SessionGuard::login stores).
 *
 * @return array<string, string>
 */
function otherDeviceSession(string $passwordHash): array
{
    /** @var SessionGuard $guard */
    $guard = Auth::guard('web');

    return ['password_hash_web' => $guard->hashPasswordForCookie($passwordHash)];
}

// -- F2 Host header ------------------------------------------------------------

it('trusts only the exact host of APP_URL', function () {
    config(['app.url' => 'https://billets.example.ci']);

    $hosts = app(TrustHosts::class)->hosts();
    expect($hosts)->toBe(['^billets\.example\.ci$']);

    Request::setTrustedHosts($hosts);
    try {
        expect(Request::create('https://billets.example.ci/connexion')->getHost())->toBe('billets.example.ci');

        foreach (['evil.example.com', 'billets.example.ci.evil.net', 'x.billets.example.ci'] as $host) {
            expect(fn () => Request::create("https://{$host}/connexion")->getHost())->toThrow(SuspiciousOperationException::class);
        }
    } finally {
        Request::setTrustedHosts([]);
    }
});

it('builds every absolute link from APP_URL in https in production', function () {
    config(['app.url' => 'https://billets.example.ci']);
    app()->detectEnvironment(fn () => 'production');

    try {
        (new AppServiceProvider(app()))->boot();

        expect(route('password.reset', ['token' => 'abc']))->toStartWith('https://billets.example.ci/')
            ->and(url('/connexion'))->toBe('https://billets.example.ci/connexion');
    } finally {
        app()->detectEnvironment(fn () => 'testing');
        URL::forceRootUrl(null);
        URL::forceScheme(null);
    }
});

// -- F7 Lockout ---------------------------------------------------------------------

it('starts a fresh counter once a lock has expired (no permanent lockout)', function () {
    $user = userWithRole(RoleName::Client, ['phone' => '+2250701020304']);
    $user->forceFill(['failed_login_attempts' => 5, 'locked_until' => now()->subMinute()])->save();

    $this->post(route('login'), ['login' => '07 01 02 03 04', 'password' => 'wrong'])
        ->assertSessionHasErrors(['login' => 'Mot de passe incorrect. Il vous reste 4 essais.']);

    expect($user->fresh())
        ->failed_login_attempts->toBe(1)
        ->locked_until->toBeNull();
});

it('still locks the account after 5 failures, then again after expiry', function () {
    $user = userWithRole(RoleName::Client, ['phone' => '+2250701020304']);
    $user->forceFill(['failed_login_attempts' => 4, 'locked_until' => null])->save();

    $this->post(route('login'), ['login' => '0701020304', 'password' => 'wrong']);
    expect($user->fresh()->isLocked())->toBeTrue();

    $this->travel((int) config('transport.security.lockout_minutes') + 1)->minutes();
    RateLimiter::clear('login:+2250701020304|127.0.0.1');
    expect($user->fresh()->isLocked())->toBeFalse();

    $this->post(route('login'), ['login' => '0701020304', 'password' => 'wrong']);
    expect($user->fresh())->failed_login_attempts->toBe(1)
        ->and($user->fresh()->isLocked())->toBeFalse();
});

it('unlocks an account from the server by phone or e-mail', function () {
    $root = superAdmin();
    $root->forceFill(['failed_login_attempts' => 5, 'locked_until' => now()->addMinutes(15), 'email' => 'chef@example.ci'])->save();

    $this->artisan('users:unlock', ['login' => $root->phone])->assertSuccessful();
    expect($root->fresh())->failed_login_attempts->toBe(0)->locked_until->toBeNull()
        ->and(Activity::query()->where('event', 'unlock')->where('subject_id', $root->id)->exists())->toBeTrue();

    $root->forceFill(['failed_login_attempts' => 5, 'locked_until' => now()->addMinutes(15)])->save();
    $this->artisan('users:unlock', ['login' => 'CHEF@example.ci'])->assertSuccessful();
    expect($root->fresh()->isLocked())->toBeFalse();

    $this->artisan('users:unlock', ['login' => '0599999999'])->assertFailed();
});

// -- F8 Environment examples -----------------------------------------------------

it('encrypts sessions by default and ships a production environment example', function () {
    $example = (string) file_get_contents(base_path('.env.example'));
    $production = (string) file_get_contents(base_path('.env.production.example'));

    expect((string) file_get_contents(config_path('session.php')))->toContain("env('SESSION_ENCRYPT', true)")
        ->and($example)->not->toMatch('/^SESSION_SECURE_COOKIE=false/m')
        ->and($example)->toMatch('/^TRUSTED_PROXIES=$/m');

    foreach (['APP_ENV=production', 'APP_DEBUG=false', 'LOG_LEVEL=warning', 'SESSION_SECURE_COOKIE=true', 'SESSION_ENCRYPT=true',
        'SESSION_DRIVER=database', 'CACHE_STORE=database', 'TRUSTED_PROXIES=', 'PAYMENT_WEBHOOK_ALLOWED_IPS=', 'APP_URL=https://'] as $line) {
        expect($production)->toMatch('/^'.preg_quote($line, '/').'/m');
    }

    // No secret in the example.
    expect($production)->toMatch('/^APP_KEY=$/m')
        ->and($production)->toMatch('/^PAYMENT_WEBHOOK_SECRET=$/m')
        ->and($production)->toMatch('/^QR_SIGNING_KEY=$/m');
});

// -- F9 Trusted proxies ------------------------------------------------------------

it('trusts no proxy by default and only the listed ones otherwise', function () {
    $forwarded = function (): Request {
        $request = Request::create('/', 'GET', server: ['REMOTE_ADDR' => '10.0.0.5', 'HTTP_X_FORWARDED_FOR' => '41.66.1.2']);
        app(TrustProxies::class)->handle($request, fn () => response('ok'));

        return $request;
    };

    try {
        expect(config('trustedproxy.proxies'))->toBe([])
            ->and($forwarded()->ip())->toBe('10.0.0.5');

        config(['trustedproxy.proxies' => ['10.0.0.0/24']]);
        expect($forwarded()->ip())->toBe('41.66.1.2');
    } finally {
        Request::setTrustedProxies([], Request::HEADER_X_FORWARDED_FOR);
    }
});

it('reads TRUSTED_PROXIES as a comma separated list', function () {
    $_ENV['TRUSTED_PROXIES'] = $_SERVER['TRUSTED_PROXIES'] = ' 10.0.0.5, 10.0.1.0/24 ,';

    try {
        expect((require config_path('trustedproxy.php'))['proxies'])->toBe(['10.0.0.5', '10.0.1.0/24']);
    } finally {
        unset($_ENV['TRUSTED_PROXIES'], $_SERVER['TRUSTED_PROXIES']);
    }

    expect((require config_path('trustedproxy.php'))['proxies'])->toBe([]);
});

// -- F11 Sessions end when the password changes ---------------------------------

it('keeps the current session after changing one’s own password, and ends the others', function () {
    $user = userWithRole(RoleName::Client);
    $oldHash = $user->password;

    $this->actingAs($user)->get(route('account.password.edit'))->assertOk();
    $this->put(route('account.password.update'), [
        'current_password' => 'password',
        'password' => 'une phrase bien longue',
        'password_confirmation' => 'une phrase bien longue',
    ])->assertSessionHasNoErrors();

    // Same browser: still logged in.
    $this->get(route('account.password.edit'))->assertOk();
    $this->assertAuthenticatedAs($user);

    // Another device, still holding the old password hash: logged out.
    $this->flushSession();
    $this->withSession(otherDeviceSession($oldHash))->actingAs($user->fresh())
        ->get(route('account.password.edit'))->assertRedirect(route('login'));
    $this->assertGuest();
});

it('ends every session of a person given a temporary password', function () {
    $client = userWithRole(RoleName::Client);
    $oldHash = $client->password;

    // Control: a session with the current hash works.
    $this->withSession(otherDeviceSession($oldHash))->actingAs($client)->get(route('account.password.edit'))->assertOk();

    app(ResetUserPassword::class)->handle(superAdmin(), $client);
    Auth::forgetGuards();
    $this->flushSession();

    $this->withSession(otherDeviceSession($oldHash))->actingAs($client->fresh())
        ->get(route('account.password.edit'))->assertRedirect(route('login'));
    $this->assertGuest();
});

it('ends every session after a password reset by e-mail', function () {
    $user = User::factory()->withEmail()->role(RoleName::Client)->create();
    $oldHash = $user->password;

    $this->post(route('password.store'), [
        'token' => Password::createToken($user),
        'email' => $user->email,
        'password' => 'nouveau mot de passe sûr',
        'password_confirmation' => 'nouveau mot de passe sûr',
    ])->assertRedirect(route('login'));

    $this->flushSession();
    $this->withSession(otherDeviceSession($oldHash))->actingAs($user->fresh())
        ->get(route('account.password.edit'))->assertRedirect(route('login'));
    $this->assertGuest();
});

// -- F12 Passwords never flashed -----------------------------------------------------

it('never flashes typed passwords back into the session', function () {
    Route::middleware('web')->post('/_test/flash', fn () => throw ValidationException::withMessages(['login' => 'Non.']));

    $this->from('/connexion')->post('/_test/flash', [
        'login' => '0701020304',
        'current_password' => 'secret-1',
        'password' => 'secret-2',
        'password_confirmation' => 'secret-3',
        'supervisor_password' => 'secret-4',
        'approver_password' => 'secret-5',
    ])->assertRedirect('/connexion');

    expect(session('_old_input'))->toBe(['login' => '0701020304']);
});

// -- F13 Supervisor check: no password oracle ---------------------------------------

describe('supervisor check', function () {
    beforeEach(function () {
        Carbon::setTestNow('2026-10-05 07:00:00');
        $setup = counterSetUp();
        $this->agent = $setup['agent'];
        $this->supervisor = userWithRole(RoleName::Gestionnaire, ['phone' => '+2250700000001']);

        $this->actingAs($this->agent)->post(route('guichet.sales.store'), ['zone_id' => $setup['anani']->id, 'payment_method' => 'cash']);
        $this->payment = Payment::query()->sole();
        $this->cancel = fn (array $overrides = []) => $this->actingAs($this->agent)->post(route('guichet.sales.cancel', $this->payment->reference), [
            'supervisor_login' => '07 00 00 00 01',
            'supervisor_password' => 'password',
            'reason' => 'Mauvaise zone choisie',
            ...$overrides,
        ]);
    });

    it('gives the same answer for a wrong password, an unknown number and a non-supervisor with the right password', function () {
        $comptable = userWithRole(RoleName::Comptable, ['phone' => '+2250700000033']);
        $bag = 'cancel_'.$this->payment->reference;

        foreach ([
            ['supervisor_password' => 'wrong'],
            ['supervisor_login' => '0500000000'],
            ['supervisor_login' => $comptable->phone],            // right password, no "tickets.cancel"
            ['supervisor_login' => $this->agent->phone],          // the agent themself
        ] as $overrides) {
            ($this->cancel)($overrides)->assertSessionHasErrorsIn($bag, ['supervisor_login' => CancelCounterSale::REFUSED_MESSAGE]);
            RateLimiter::clear('supervisor-cancel:'.$this->agent->id);
        }

        expect($this->payment->fresh()->status->value)->toBe('succeeded')
            // A non-supervisor's account is not touched (it is "unknown" here).
            ->and($comptable->fresh()->failed_login_attempts)->toBe(0)
            ->and(Activity::query()->where('log_name', 'audit')->where('event', 'sale.cancel-refused')->count())->toBe(4)
            ->and(json_encode(Activity::query()->where('event', 'sale.cancel-refused')->pluck('properties')))->not->toContain('wrong');
    });

    it('counts a wrong password on the supervisor account and locks it like the login form', function () {
        foreach (range(1, 5) as $attempt) {
            ($this->cancel)(['supervisor_password' => 'wrong']);
        }

        expect($this->supervisor->fresh()->isLocked())->toBeTrue()
            ->and(Activity::query()->where('event', 'lockout')->where('subject_id', $this->supervisor->id)->exists())->toBeTrue();

        // Locked: even the right password is refused, with the same message.
        RateLimiter::clear('supervisor-cancel:'.$this->agent->id);
        ($this->cancel)()->assertSessionHasErrorsIn('cancel_'.$this->payment->reference, ['supervisor_login' => CancelCounterSale::REFUSED_MESSAGE]);
        expect($this->payment->fresh()->status->value)->toBe('succeeded');
    });

    it('still cancels with the right supervisor credentials', function () {
        ($this->cancel)()->assertSessionHas('success');

        expect($this->payment->fresh()->status->value)->toBe('cancelled');
    });
});

// -- F4 No privilege escalation by a manager -------------------------------------------

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function hardeningUserForm(User $user, array $overrides = []): array
{
    return [
        'first_name' => $user->first_name,
        'last_name' => $user->last_name,
        'phone' => $user->phone,
        'email' => $user->email ?? '',
        'role' => $user->getRoleNames()->first(),
        'status' => $user->status->value,
        ...$overrides,
    ];
}

it('lets a manager give only roles whose rights they all hold', function (string $role, bool $allowed) {
    $manager = userWithRole(RoleName::Gestionnaire);

    $response = $this->actingAs($manager)->post(route('admin.users.store'), [
        'first_name' => 'Grâce', 'last_name' => 'Kouamé', 'phone' => '05 11 22 33 44', 'email' => '', 'role' => $role,
    ]);

    $allowed
        ? $response->assertSessionHasNoErrors()
        : $response->assertSessionHasErrors(['role' => 'Vous ne pouvez pas attribuer ce rôle.']);
    expect(User::query()->where('phone', '+2250511223344')->exists())->toBe($allowed);
})->with([
    'client' => ['client', true],
    'gestionnaire' => ['gestionnaire', true],
    'guichetier' => ['guichetier', false],
    'contrôleur' => ['controleur', false],
    'comptable' => ['comptable', false],
    'direction (read only, a subset of the manager rights)' => ['direction', true],
]);

it('lets the super-admin give any staff role', function () {
    $this->actingAs(superAdmin())->post(route('admin.users.store'), [
        'first_name' => 'Grâce', 'last_name' => 'Kouamé', 'phone' => '05 11 22 33 44', 'email' => '', 'role' => 'comptable',
    ])->assertSessionHasNoErrors();

    expect(User::query()->where('phone', '+2250511223344')->sole()->getRoleNames()->all())->toBe(['comptable']);
});

it('lets a manager edit a staff member without changing their login, role kept', function () {
    $manager = userWithRole(RoleName::Gestionnaire);
    $agent = userWithRole(RoleName::Guichetier, ['phone' => '+2250701010101']);

    $page = $this->actingAs($manager)->get(route('admin.users.edit', $agent))->assertOk();
    $page->assertSee('readonly', false)
        ->assertDontSee('Donner un mot de passe provisoire')
        ->assertDontSee('<option value="controleur"', false)
        ->assertSee('<option value="guichetier" selected', false);

    // Renaming with the role kept is fine.
    $this->put(route('admin.users.update', $agent), hardeningUserForm($agent, ['first_name' => 'Awa', 'phone' => '07 01 01 01 01']))
        ->assertSessionHasNoErrors();
    expect($agent->fresh()->first_name)->toBe('Awa');

    // Phone or e-mail: refused.
    $this->put(route('admin.users.update', $agent), hardeningUserForm($agent, ['phone' => '0709090909']))
        ->assertSessionHasErrors(['phone' => 'Seul un super-administrateur peut changer le numéro d’un membre du personnel.']);
    $this->put(route('admin.users.update', $agent), hardeningUserForm($agent, ['email' => 'pirate@example.ci']))
        ->assertSessionHasErrors('email');
    // Promotion to a role with rights the manager lacks: refused.
    $this->put(route('admin.users.update', $agent), hardeningUserForm($agent, ['role' => 'controleur']))
        ->assertSessionHasErrors('role');

    expect($agent->fresh())->phone->toBe('+2250701010101')->email->toBeNull()
        ->and($agent->fresh()->getRoleNames()->all())->toBe(['guichetier']);
});

it('reserves the temporary password of a staff account to the super-admin', function () {
    $manager = userWithRole(RoleName::Gestionnaire);
    $agent = userWithRole(RoleName::Guichetier);
    $client = userWithRole(RoleName::Client);

    $this->actingAs($manager)->post(route('admin.users.password.reset', $agent))->assertForbidden();
    $this->actingAs($manager)->post(route('admin.users.password.reset', userWithRole(RoleName::Gestionnaire)))->assertForbidden();
    $this->actingAs($manager)->post(route('admin.users.password.reset', $client))->assertRedirect(route('admin.users.edit', $client));
    $this->actingAs(superAdmin())->post(route('admin.users.password.reset', $agent))->assertRedirect(route('admin.users.edit', $agent));

    expect($agent->fresh()->must_change_password)->toBeTrue();
});

it('lets the super-admin change a staff login and logs old and new values', function () {
    $agent = userWithRole(RoleName::Guichetier, ['phone' => '+2250701010101']);

    $this->actingAs(superAdmin())->put(route('admin.users.update', $agent), hardeningUserForm($agent, [
        'phone' => '07 09 09 09 09', 'email' => 'agent@example.ci', 'role' => 'controleur',
    ]))->assertSessionHasNoErrors();

    expect(Activity::query()->where('event', 'user.identity-changed')->sole()->properties->all())->toBe([
        'phone' => ['old' => '+2250701010101', 'new' => '+2250709090909'],
        'email' => ['old' => null, 'new' => 'agent@example.ci'],
    ])->and(Activity::query()->where('event', 'user.role-changed')->sole()->properties->all())->toBe(['old' => 'guichetier', 'new' => 'controleur']);
});

it('lets a manager still change a customer phone number, logged', function () {
    $manager = userWithRole(RoleName::Gestionnaire);
    $client = userWithRole(RoleName::Client, ['phone' => '+2250701010101']);

    $this->actingAs($manager)->put(route('admin.users.update', $client), hardeningUserForm($client, ['phone' => '0709090909']))
        ->assertSessionHasNoErrors();

    expect($client->fresh()->phone)->toBe('+2250709090909')
        ->and(Activity::query()->where('event', 'user.identity-changed')->sole()->causer_id)->toBe($manager->id);
});
