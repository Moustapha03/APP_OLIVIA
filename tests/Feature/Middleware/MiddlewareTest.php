<?php

use App\Enums\RoleName;
use App\Enums\UserStatus;
use Spatie\Activitylog\Models\Activity;

// -- EnsureUserIsActive -------------------------------------------------------

it('logs out a user disabled during their session', function () {
    $user = userWithRole(RoleName::Guichetier);
    $this->actingAs($user)->get(route('guichet.cash.show'))->assertOk();

    $user->forceFill(['status' => UserStatus::Disabled])->save();

    $this->get(route('guichet.cash.show'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('error', 'Votre compte a été désactivé. Contactez la compagnie de transport.');

    $this->assertGuest();
});

it('answers 401 in JSON to a disabled user', function () {
    $user = userWithRole(RoleName::Controleur);
    $user->forceFill(['status' => UserStatus::Disabled])->save();

    $this->actingAs($user)->getJson(route('api.me'))->assertUnauthorized();
});

// -- SecurityHeaders ----------------------------------------------------------

it('sends the security headers', function () {
    $response = $this->get(route('home'))->assertOk();

    $csp = (string) $response->headers->get('Content-Security-Policy');

    expect($csp)
        ->toContain("default-src 'self'")
        ->toContain("frame-ancestors 'none'")
        ->toContain("object-src 'none'")
        ->toMatch("/script-src 'self' 'nonce-[A-Za-z0-9]+'/")
        ->not->toContain('unsafe-eval')
        ->not->toContain('unsafe-inline');

    $response->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeaderMissing('Strict-Transport-Security');
});

it('puts the CSP nonce on the Vite script tags', function () {
    $response = $this->get(route('login'));
    preg_match("/'nonce-([A-Za-z0-9]+)'/", (string) $response->headers->get('Content-Security-Policy'), $match);

    expect($match[1] ?? null)->not->toBeNull()
        ->and($response->getContent())->toContain('nonce="'.$match[1].'"');
});

it('allows the camera on the controller app only', function () {
    expect($this->get(route('home'))->headers->get('Permissions-Policy'))->toContain('camera=()');

    $this->actingAs(userWithRole(RoleName::Controleur))
        ->get(route('controle.scan'))
        ->assertHeader('Permissions-Policy', 'camera=(self), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()');
});

// -- VerifyWebhookSignature (details in Purchase/PaymentWebhookTest) ------------

it('rejects every webhook when no secret is configured (fails closed)', function () {
    config(['services.payment.webhook_secret' => '']);
    $body = '{"reference":"TTX"}';

    $this->call('POST', route('webhooks.payments', 'fake'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_SIGNATURE' => hash_hmac('sha256', $body, ''),
    ], $body)->assertUnauthorized();
});

it('does not require a CSRF token for a signed webhook', function () {
    $body = '{"reference":"TTX"}';

    $this->call('POST', route('webhooks.payments', 'fake'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_SIGNATURE' => hash_hmac('sha256', $body, 'testing-webhook-secret'),
    ], $body)->assertOk();
});

it('refuses an unknown gateway', function () {
    $this->postJson('/webhooks/paiements/paypal', [])->assertNotFound();
});

// -- LogSensitiveAction -------------------------------------------------------

it('logs a successful sensitive action, not a failed validation', function () {
    $user = userWithRole(RoleName::Client);

    $this->actingAs($user)->put(route('account.password.update'), ['current_password' => 'wrong'])->assertSessionHasErrors();
    expect(Activity::query()->where('log_name', 'audit')->count())->toBe(0);

    $this->actingAs($user)->put(route('account.password.update'), [
        'current_password' => 'password',
        'password' => 'une phrase bien longue',
        'password_confirmation' => 'une phrase bien longue',
    ]);

    $entry = Activity::query()->where('log_name', 'audit')->sole();
    expect($entry->causer_id)->toBe($user->id)
        ->and($entry->properties['route'])->toBe('account.password.update')
        ->and($entry->properties['method'])->toBe('PUT')
        ->and($entry->properties['input'])->toBe([]);
});

// -- Throttling ---------------------------------------------------------------

it('rate limits the JSON API per user', function () {
    $user = userWithRole(RoleName::Controleur);

    foreach (range(1, 60) as $call) {
        $this->actingAs($user)->getJson(route('api.me'))->assertOk();
    }

    $this->actingAs($user)->getJson(route('api.me'))->assertTooManyRequests();
});

it('returns only minimal data from /api/me', function () {
    $user = userWithRole(RoleName::Controleur, ['first_name' => 'Konan']);

    $this->actingAs($user)->getJson(route('api.me'))
        ->assertExactJson(['id' => $user->id, 'first_name' => 'Konan', 'spaces' => ['controle']]);
});
