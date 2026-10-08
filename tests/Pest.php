<?php

use App\Enums\FareType;
use App\Enums\RoleName;
use App\Models\CashSession;
use App\Models\Fare;
use App\Models\Line;
use App\Models\Stop;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function superAdmin(): User
{
    return userWithRole(RoleName::SuperAdmin);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function userWithRole(RoleName $role, array $attributes = []): User
{
    return User::factory()->role($role)->create($attributes);
}

/**
 * The agency's network, as in the client's grid (towards Angré):
 *   zone 1 Koumassi / Marcory      jour   500 · mois 30 000 (no "simple" price)
 *   zone 2 Anani / Port-Bouët      jour 1 000 · mois 40 000 · simple 30 000
 *   zone 3 Bonoua / Bassam         jour 1 500 · mois 50 000 · simple 30 000
 *
 * @return array{line: Line, marcory: Zone, anani: Zone, bassam: Zone, stops: array<string, Stop>}
 */
function networkSetUp(): array
{
    $line = Line::factory()->create(['name' => 'Bonoua – Angré', 'destination_name' => 'Angré']);

    $zones = [];
    $stops = [];
    foreach ([
        'marcory' => ['Koumassi / Marcory', ['Marcory'], ['daily' => 500, 'monthly' => 30000]],
        'anani' => ['Anani / Port-Bouët', ['Anani', 'Carrefour Aéroport'], ['daily' => 1000, 'monthly' => 40000, 'monthly_morning' => 30000, 'monthly_evening' => 30000]],
        'bassam' => ['Bonoua / Bassam', ['Zouglou', 'Bonoua'], ['daily' => 1500, 'monthly' => 50000, 'monthly_morning' => 30000, 'monthly_evening' => 30000]],
    ] as $key => [$name, $stopNames, $fares]) {
        $zones[$key] = Zone::factory()->for($line)->create(['name' => $name]);
        foreach ($stopNames as $stopName) {
            $stops[$stopName] = Stop::factory()->for($zones[$key])->create(['name' => $stopName]);
        }
        foreach ($fares as $type => $amount) {
            Fare::factory()->for($zones[$key])->type(FareType::from($type), $amount)->create(['effective_from' => '2026-01-01']);
        }
    }

    return ['line' => $line, ...$zones, 'stops' => $stops];
}

/**
 * Agency desk set-up: the network + an agent with an open register (float 20 000).
 *
 * @return array{line: Line, marcory: Zone, anani: Zone, bassam: Zone, stops: array<string, Stop>, agent: User, session: CashSession}
 */
function counterSetUp(): array
{
    $network = networkSetUp();
    $agent = userWithRole(RoleName::Guichetier);
    $session = CashSession::factory()->for($agent)->create(['opening_float' => 20000]);

    return [...$network, 'agent' => $agent, 'session' => $session];
}
