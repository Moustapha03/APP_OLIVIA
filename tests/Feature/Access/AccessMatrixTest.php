<?php

use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Models\CashSession;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Stop;
use App\Models\Subscriber;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Vehicle;

/*
| Every role against every GET route of every space.
| Allowed = 200, or a redirect such as "open your cash register first". Forbidden = 403. Guests are sent to the login page.
| Add each new route here: a route missing from this list fails the
| "every route is covered" test below.
*/

/** @return array<string, list<RoleName>> route name => roles allowed */
function accessMatrix(): array
{
    $admin = [RoleName::SuperAdmin, RoleName::Gestionnaire, RoleName::Direction, RoleName::Comptable];
    $everyone = RoleName::cases();
    $readNetwork = [RoleName::SuperAdmin, RoleName::Gestionnaire, RoleName::Direction];
    $manage = [RoleName::SuperAdmin, RoleName::Gestionnaire];
    $receipts = [RoleName::SuperAdmin, RoleName::Gestionnaire, RoleName::Direction, RoleName::Comptable];

    return [
        'dashboard' => $everyone,
        'account.password.edit' => $everyone,
        'api.me' => $everyone,

        'espace.home' => [RoleName::SuperAdmin, RoleName::Client],
        'espace.profile.edit' => [RoleName::SuperAdmin, RoleName::Client],
        'espace.purchase.create' => [RoleName::SuperAdmin, RoleName::Client],
        'espace.purchase.formula' => [RoleName::SuperAdmin, RoleName::Client],
        'espace.passes.index' => [RoleName::SuperAdmin, RoleName::Client],
        'espace.subscribers.index' => [RoleName::SuperAdmin, RoleName::Client],

        'admin.dashboard' => $admin,

        // Reference data: read = gestionnaire + direction, write = gestionnaire.
        'admin.lines.index' => $readNetwork,
        'admin.lines.show' => $readNetwork,
        'admin.lines.create' => $manage,
        'admin.lines.edit' => $manage,
        'admin.lines.zones.edit' => $manage,
        'admin.lines.zones.stops.edit' => $manage,
        'admin.fares.index' => $readNetwork,
        'admin.fares.create' => $manage,
        'admin.vehicles.index' => $readNetwork,
        'admin.vehicles.create' => $manage,
        'admin.vehicles.edit' => $manage,

        'admin.users.index' => $manage,
        'admin.users.create' => $manage,
        'admin.users.edit' => $manage,
        'admin.roles.index' => [RoleName::SuperAdmin],
        'admin.receipts.index' => $receipts,
        'admin.receipts.export' => $receipts,
        'admin.expenses.index' => $receipts,
        'admin.expenses.export' => $receipts,
        'admin.expenses.create' => [RoleName::SuperAdmin, RoleName::Gestionnaire, RoleName::Comptable],
        'admin.expenses.edit' => [RoleName::SuperAdmin, RoleName::Gestionnaire, RoleName::Comptable],
        'admin.receipts.show' => $receipts,
        'admin.receipts.print' => $receipts,
        'admin.subscribers.index' => $receipts,
        'admin.subscribers.show' => $receipts,
        'admin.subscribers.create' => $manage,
        'admin.subscribers.import' => $manage,
        'admin.subscribers.import.template' => $manage,
        'admin.subscribers.edit' => $manage,

        'guichet.home' => [RoleName::SuperAdmin, RoleName::Guichetier],
        'guichet.sales.create' => [RoleName::SuperAdmin, RoleName::Guichetier],
        'guichet.cash.show' => [RoleName::SuperAdmin, RoleName::Guichetier],
        'guichet.customers.index' => [RoleName::SuperAdmin, RoleName::Guichetier],
        'guichet.subscriptions.create' => [RoleName::SuperAdmin, RoleName::Guichetier],
        'guichet.subscribers.index' => [RoleName::SuperAdmin, RoleName::Guichetier],
        'guichet.subscribers.edit' => [RoleName::SuperAdmin, RoleName::Guichetier],

        'controle.home' => [RoleName::SuperAdmin, RoleName::Controleur],
        'controle.scan' => [RoleName::SuperAdmin, RoleName::Controleur],
        'controle.scans.index' => [RoleName::SuperAdmin, RoleName::Controleur],
        'controle.trips.current' => [RoleName::SuperAdmin, RoleName::Controleur],
        'api.controle.manifest' => [RoleName::SuperAdmin, RoleName::Controleur],

        'design.index' => [RoleName::SuperAdmin],
    ];
}

/**
 * URL of a matrix route, creating the records its parameters need.
 */
function matrixUrl(string $route): string
{
    $needs = [
        'admin.lines.show' => ['line'],
        'admin.lines.edit' => ['line'],
        'admin.lines.zones.edit' => ['line', 'zone'],
        'admin.lines.zones.stops.edit' => ['line', 'zone', 'stop'],
        'guichet.subscribers.edit' => ['subscriber'],
        'admin.subscribers.show' => ['subscriber'],
        'admin.subscribers.edit' => ['subscriber'],
        'admin.expenses.edit' => ['expense'],
        'admin.receipts.show' => ['payment'],
        'admin.receipts.print' => ['payment'],
        'admin.vehicles.edit' => ['vehicle'],
        'admin.users.edit' => ['user'],
        'espace.purchase.formula' => ['stop'],
    ][$route] ?? [];

    if ($needs === []) {
        return route($route);
    }

    $stop = Stop::factory()->create();
    $records = [
        'line' => $stop->zone->line,
        'zone' => $stop->zone,
        'stop' => $stop,
        'subscriber' => Subscriber::factory()->create(),
        'vehicle' => Vehicle::factory()->create(),
        'user' => userWithRole(RoleName::Client),
        'payment' => matrixPayment(),
        'expense' => Expense::factory()->create(),
    ];

    return route($route, array_intersect_key($records, array_flip($needs)));
}

/**
 * A paid receipt from the imported history, with its subscription.
 */
function matrixPayment(): Payment
{
    $subscription = Subscription::factory()->create();
    $payment = Payment::factory()->monthly()->create(['gateway' => 'import', 'status' => PaymentStatus::Succeeded, 'receipt_number' => 'BA-2026-0001']);
    $payment->payable()->associate($subscription)->save();

    return $payment;
}

/**
 * Not part of the matrix: public or guest-only routes, and routes whose
 * access depends on WHO owns the record (one customer vs another: covered
 * by the IDOR tests in Espace/PassesTest, Guichet/SubscriberTest, Purchase/*).
 */
const PUBLIC_ROUTES = [
    'home', 'offline', 'login', 'register', 'password.request', 'password.reset', 'verification.verify', 'password.confirm', 'design.layout', 'design.scan',
    'espace.payments.show', 'espace.tickets.show', 'espace.tickets.pdf', 'espace.subscriptions.show', 'espace.subscriptions.pdf', 'photos.subscriber', 'payments.fake.show',
    'guichet.sales.receipt', 'guichet.cash.report', 'admin.expenses.attachment', 'receipts.pdf', 'receipts.whatsapp', 'receipts.shared',
];

dataset('matrix', function () {
    foreach (accessMatrix() as $route => $allowed) {
        foreach (RoleName::cases() as $role) {
            yield "{$role->value} → {$route}" => [$route, $role, in_array($role, $allowed, true)];
        }
    }
});

it('enforces the access matrix', function (string $route, RoleName $role, bool $allowed) {
    $url = matrixUrl($route);
    $response = $this->actingAs(userWithRole($role))->get($url);

    // Allowed: the page works (200) or sends elsewhere (302), never an error.
    $allowed
        ? expect($response->getStatusCode())->toBeIn([200, 302])
        : $response->assertForbidden();
})->with('matrix');

it('sends guests to the login page', function (string $route) {
    $this->get(matrixUrl($route))->assertRedirect(route('login'));
})->with(array_keys(accessMatrix()));

it('covers every GET route of the application', function () {
    $routes = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn ($route) => in_array('GET', $route->methods(), true) && $route->getName() !== null)
        ->map(fn ($route) => $route->getName())
        ->reject(fn (string $name) => in_array($name, PUBLIC_ROUTES, true) || str_starts_with($name, 'storage.') || $name === 'up')
        ->values();

    expect($routes->diff(array_keys(accessMatrix()))->values()->all())->toBe([]);
});

// Changing one's own password and /api/me stay open to any logged-in account.
it('forbids every space to a user without any role', function (string $route) {
    $url = matrixUrl($route);
    $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
})->with(array_values(array_diff(array_keys(accessMatrix()), ['account.password.edit', 'api.me'])));

it('forbids state-changing customer routes to staff', function (RoleName $role) {
    $user = userWithRole($role);

    $this->actingAs($user)->patch(route('espace.profile.update'), ['first_name' => 'X', 'last_name' => 'Y'])->assertForbidden();
    $this->actingAs($user)->delete(route('espace.profile.destroy'), ['password' => 'password'])->assertForbidden();
    expect($user->fresh())->not->toBeNull();
})->with([RoleName::Gestionnaire, RoleName::Direction, RoleName::Comptable, RoleName::Guichetier, RoleName::Controleur]);

it('lands each role on the home of its space', function (RoleName $role, string $home) {
    $this->actingAs(userWithRole($role))->get(route('dashboard'))->assertRedirect(route($home));
})->with([
    [RoleName::SuperAdmin, 'admin.dashboard'],
    [RoleName::Gestionnaire, 'admin.dashboard'],
    [RoleName::Direction, 'admin.dashboard'],
    [RoleName::Comptable, 'admin.dashboard'],
    [RoleName::Guichetier, 'guichet.home'],
    [RoleName::Controleur, 'controle.home'],
    [RoleName::Client, 'espace.home'],
]);

it('lets a ticket agent sell only with an open cash register', function () {
    $agent = userWithRole(RoleName::Guichetier);

    $this->actingAs($agent)->get(route('guichet.sales.create'))
        ->assertRedirect(route('guichet.cash.show'))
        ->assertSessionHas('warning', 'Ouvrez votre caisse avant de vendre.');

    CashSession::factory()->closed()->for($agent)->create();
    $this->actingAs($agent)->get(route('guichet.sales.create'))->assertRedirect(route('guichet.cash.show'));

    CashSession::factory()->for($agent)->create();
    $this->actingAs($agent)->get(route('guichet.sales.create'))->assertOk()->assertSee('Caisse ouverte');
});
