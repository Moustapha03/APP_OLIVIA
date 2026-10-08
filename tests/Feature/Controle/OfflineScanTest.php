<?php

use App\Enums\FareType;
use App\Enums\PassStatus;
use App\Enums\RoleName;
use App\Enums\ScanResult;
use App\Models\Payment;
use App\Models\Scan;
use App\Models\Subscriber;
use App\Models\Subscription;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Qr\PassSigner;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Carbon::setTestNow('2026-10-06 07:30:00');
    $this->network = networkSetUp();
    $this->controller = userWithRole(RoleName::Controleur);
    $this->subscriber = Subscriber::factory()->create(['full_name' => 'BROU NESTOR', 'phone' => '+2250701020304', 'stop_id' => $this->network['stops']['Zouglou']->id]);
});

function offlineTicket(array $attributes = []): Ticket
{
    return Ticket::factory()->create([
        'user_id' => null,
        'zone_id' => test()->network['anani']->id,
        'stop_id' => test()->network['stops']['Anani']->id,
        ...$attributes,
    ]);
}

function offlineQr(Ticket|Subscription $pass): string
{
    return app(PassSigner::class)->sign($pass);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function offlineItem(string $code, array $overrides = []): array
{
    return [
        'client_id' => (string) Str::uuid(),
        'code' => $code,
        'result' => 'valid',
        'reason_code' => 'ok',
        'scanned_at' => '2026-10-06T07:10:00Z',
        'period' => 'morning',
        ...$overrides,
    ];
}

/**
 * @param  list<array<string, mixed>>  $items
 */
function syncScans(array $items, ?User $as = null): TestResponse
{
    return test()->actingAs($as ?? test()->controller)->postJson(route('api.controle.scans.sync'), ['scans' => $items]);
}

// -- Manifest -----------------------------------------------------------------------

it('lists only the passes valid today, without photo nor phone number', function () {
    $current = Subscription::factory()->for($this->subscriber)->create(['formula' => FareType::MonthlyMorning]);
    $payment = Payment::factory()->monthly()->succeeded()->create(['receipt_number' => 'BA-2026-0017', 'zone_id' => $current->zone_id]);
    $current->forceFill(['payment_id' => $payment->id])->save();

    Subscription::factory()->for($this->subscriber)->create(['starts_on' => '2026-09-01', 'ends_on' => '2026-09-30']);
    Subscription::factory()->for($this->subscriber)->create(['starts_on' => '2026-11-01', 'ends_on' => '2026-11-30']);
    Subscription::factory()->for($this->subscriber)->create(['status' => PassStatus::Cancelled]);

    $ticket = offlineTicket(['rides_used' => 1]);
    offlineTicket(['rides_used' => 2]);                       // used up
    offlineTicket(['valid_on' => '2026-10-05']);               // yesterday
    offlineTicket(['status' => PassStatus::Refunded]);

    $response = $this->actingAs($this->controller)->getJson(route('api.controle.manifest'))
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('user_id', $this->controller->id)
        ->assertJsonPath('valid_for', '2026-10-06')
        ->assertJsonPath('evening_starts_at', '12:00')
        ->assertJsonPath('anti_replay_minutes', 30)
        ->assertJsonCount(2, 'passes')
        ->assertJsonPath('passes.0', [
            'kind' => 's',
            'uuid' => $current->uuid,
            'receipt_number' => 'BA-2026-0017',
            'holder' => 'BROU NESTOR',
            'formula' => 'Matin simple',
            'periods' => ['morning'],
            'zone' => 'Bonoua / Bassam',
            'boarding_point' => 'Zouglou',
            'valid' => 'octobre 2026',
            'rides_allowed' => null,
            'rides_used' => null,
            'has_photo' => true,
            'last_accepted_at' => null,
        ])
        ->assertJsonPath('passes.1.kind', 't')
        ->assertJsonPath('passes.1.uuid', $ticket->uuid)
        ->assertJsonPath('passes.1.valid', '06/10/2026')
        ->assertJsonPath('passes.1.periods', ['morning', 'evening'])
        ->assertJsonPath('passes.1.rides_allowed', 2)
        ->assertJsonPath('passes.1.rides_used', 1);

    $json = $response->getContent();
    expect($json)->not->toContain('0701020304')
        ->not->toContain('photos/')
        ->not->toContain('phone')
        ->not->toContain((string) (config('transport.qr.key') ?: 'no-key-in-config'));

    expect(Activity::query()->where('event', 'scans.manifest')->sole())
        ->log_name->toBe('audit')
        ->causer_id->toBe($this->controller->id);
});

it('tells the phone when a pass was accepted a moment ago (anti-replay offline)', function () {
    $subscription = Subscription::factory()->for($this->subscriber)->create();
    $other = Subscription::factory()->for($this->subscriber)->create();
    $scan = fn (Subscription $pass, string $at, string $code) => (new Scan)->forceFill([
        'user_id' => $this->controller->id, 'kind' => 'subscription', 'subscription_id' => $pass->id,
        'result' => $code === 'ok' ? 'valid' : 'refused', 'reason_code' => $code, 'reason' => '-', 'period' => 'morning', 'scanned_at' => $at,
    ])->save();
    $scan($subscription, '2026-10-06 07:20:00', 'ok');
    $scan($other, '2026-10-06 06:30:00', 'ok');          // outside the 30 min window
    $scan($other, '2026-10-06 07:25:00', 'wrong_period'); // not accepted

    $passes = collect($this->actingAs($this->controller)->getJson(route('api.controle.manifest'))->assertOk()->json('passes'))->keyBy('uuid');

    expect($passes[$subscription->uuid]['last_accepted_at'])->toBe('2026-10-06T07:20:00+00:00')
        ->and($passes[$other->uuid]['last_accepted_at'])->toBeNull();
});

it('forbids the manifest and the sync to other roles', function (RoleName $role) {
    $user = userWithRole($role);

    $this->actingAs($user)->getJson(route('api.controle.manifest'))->assertForbidden();
    syncScans([offlineItem('BA-2026-0017')], $user)->assertForbidden();
    expect(Scan::query()->count())->toBe(0);
})->with([RoleName::Client, RoleName::Guichetier, RoleName::Comptable]);

it('sends guests to the login page', function () {
    $this->get(route('api.controle.manifest'))->assertRedirect(route('login'));
    $this->getJson(route('api.controle.manifest'))->assertUnauthorized();
    $this->postJson(route('api.controle.scans.sync'), ['scans' => [offlineItem('BA-2026-0017')]])->assertUnauthorized();
});

it('exposes the offline settings to the scanner page', function () {
    $this->actingAs($this->controller)->get(route('controle.scan'))
        ->assertOk()
        ->assertSee('data-user-id="'.$this->controller->id.'"', false)
        ->assertSee(route('api.controle.manifest'))
        ->assertSee(route('api.controle.scans.sync'))
        ->assertSee('data-offline-result', false);
});

// -- Sync -----------------------------------------------------------------------------

it('records offline scans at the time they were made, once only', function () {
    $subscription = Subscription::factory()->for($this->subscriber)->create();
    $items = [offlineItem(offlineQr($subscription), ['result' => 'warning', 'reason_code' => 'photo_offline'])];

    syncScans($items)
        ->assertOk()
        ->assertJsonPath('created', 1)
        ->assertJsonPath('results.0.client_id', $items[0]['client_id'])
        ->assertJsonPath('results.0.status', 'created')
        ->assertJsonPath('results.0.result', 'valid')
        ->assertJsonPath('results.0.reason_code', 'ok');

    // The same batch again (lost answer, second tab…): nothing new.
    syncScans($items)->assertOk()->assertJsonPath('created', 0)->assertJsonPath('duplicates', 1)
        ->assertJsonPath('results.0.status', 'duplicate');

    expect(Scan::query()->sole())
        ->source->toBe('offline')
        ->client_id->toBe($items[0]['client_id'])
        ->device_result->toBe(ScanResult::Warning)
        ->device_reason_code->toBe('photo_offline')
        ->subscription_id->toBe($subscription->id)
        ->user_id->toBe($this->controller->id)
        ->and(Scan::query()->sole()->scanned_at->toDateTimeString())->toBe('2026-10-06 07:10:00')
        ->and(Scan::query()->sole()->synced_at?->toDateTimeString())->toBe('2026-10-06 07:30:00');

    expect(Activity::query()->where('event', 'scans.sync')->count())->toBe(2);
});

it('counts one ride per accepted offline scan while rides remain', function () {
    Carbon::setTestNow('2026-10-06 10:00:00');
    $ticket = offlineTicket(['rides_allowed' => 2, 'rides_used' => 1]);
    $code = offlineQr($ticket);

    syncScans([
        offlineItem($code, ['scanned_at' => '2026-10-06T06:00:00Z']),
        // 2 hours later (after the anti-replay window), the phone still accepted it:
        // no ride left on the server.
        offlineItem($code, ['scanned_at' => '2026-10-06T08:00:00Z']),
    ])->assertOk()->assertJsonPath('created', 2);

    expect($ticket->fresh()->rides_used)->toBe(2)
        ->and(Scan::query()->orderBy('scanned_at')->pluck('reason_code')->all())->toBe(['ok', 'used_up']);
});

it('does not count a ride when the phone refused the passenger', function () {
    $ticket = offlineTicket();

    syncScans([offlineItem(offlineQr($ticket), ['result' => 'refused', 'reason_code' => 'unknown'])])
        ->assertOk()
        ->assertJsonPath('results.0.result', 'refused')
        ->assertJsonPath('results.0.reason_code', 'offline_refused');

    expect($ticket->fresh()->rides_used)->toBe(0);
});

it('applies the anti-replay window between offline scans', function () {
    $ticket = offlineTicket(['rides_allowed' => 4]);
    $code = offlineQr($ticket);

    syncScans([
        offlineItem($code, ['scanned_at' => '2026-10-06T07:05:00Z']),
        offlineItem($code, ['scanned_at' => '2026-10-06T06:50:00Z']),
    ])->assertOk();

    // Sorted by time: 06:50 accepted, 07:05 is a replay (no second ride).
    expect($ticket->fresh()->rides_used)->toBe(1)
        ->and(Scan::query()->orderBy('scanned_at')->pluck('reason_code')->all())->toBe(['ok', 'replay']);
});

it('records refused offline scans and checks the period at the time of the scan', function () {
    $evening = Subscription::factory()->for($this->subscriber)->create(['formula' => FareType::MonthlyEvening]);

    syncScans([
        offlineItem(offlineQr($evening), ['result' => 'refused', 'reason_code' => 'wrong_period', 'scanned_at' => '2026-10-06T06:40:00Z']),
    ])->assertOk()->assertJsonPath('results.0.result', 'refused')->assertJsonPath('results.0.reason_code', 'wrong_period');

    expect(Scan::query()->sole())
        ->result->toBe(ScanResult::Refused)
        ->device_result->toBe(ScanResult::Refused)
        ->source->toBe('offline');
});

it('records unknown and forged codes as refused', function () {
    $ticket = offlineTicket();
    $forged = 'TT1.t.'.$ticket->uuid.'.k1.'.str_repeat('A', 43);

    syncScans([
        offlineItem('TT1.s.'.Str::uuid().'.k1.abc', ['result' => 'refused', 'reason_code' => 'unknown']),
        offlineItem($forged),
        offlineItem('BA-2026-9999', ['result' => 'refused', 'reason_code' => 'unknown']),
    ])->assertOk()->assertJsonPath('created', 3);

    expect(Scan::query()->pluck('reason_code')->unique()->all())->toBe(['unknown'])
        ->and(Scan::query()->pluck('kind')->unique()->all())->toBe(['unknown'])
        ->and($ticket->fresh()->rides_used)->toBe(0)
        ->and(Activity::query()->where('event', 'scan.unknown')->count())->toBe(3);
});

it('rejects malformed batches', function (array $items, string $error) {
    syncScans($items)->assertUnprocessable()->assertJsonValidationErrors($error);
    expect(Scan::query()->count())->toBe(0);
})->with([
    'no scan' => [[], 'scans'],
    'bad client id' => [[offlineItem('BA-2026-0017', ['client_id' => 'abc'])], 'scans.0.client_id'],
    'same client id twice' => [[offlineItem('X', ['client_id' => 'b1f0c1de-0000-4000-8000-000000000001']), offlineItem('Y', ['client_id' => 'B1F0C1DE-0000-4000-8000-000000000001'])], 'scans.1.client_id'],
    'missing code' => [[offlineItem('', [])], 'scans.0.code'],
    'code too long' => [[offlineItem(str_repeat('A', 201))], 'scans.0.code'],
    'bad result' => [[offlineItem('BA-2026-0017', ['result' => 'ok'])], 'scans.0.result'],
    'bad reason' => [[offlineItem('BA-2026-0017', ['reason_code' => 'whatever'])], 'scans.0.reason_code'],
    'bad date' => [[offlineItem('BA-2026-0017', ['scanned_at' => 'hier'])], 'scans.0.scanned_at'],
    'in the future' => [[offlineItem('BA-2026-0017', ['scanned_at' => '2026-10-06T09:00:00Z'])], 'scans.0.scanned_at'],
    'too old' => [[offlineItem('BA-2026-0017', ['scanned_at' => '2026-09-20T07:00:00Z'])], 'scans.0.scanned_at'],
    'bad period' => [[offlineItem('BA-2026-0017', ['period' => 'night'])], 'scans.0.period'],
    'unexpected field' => [[offlineItem('BA-2026-0017', ['ticket_id' => 1])], 'scans.0'],
]);

it('accepts at most 200 scans per request', function () {
    $items = array_map(fn () => offlineItem('BA-2026-9999', ['result' => 'refused', 'reason_code' => 'unknown']), range(1, 201));

    syncScans($items)->assertUnprocessable()->assertJsonValidationErrors('scans');
    expect(Scan::query()->count())->toBe(0);

    syncScans(array_slice($items, 0, 200))->assertOk()->assertJsonPath('created', 200);
});

it('still checks passes online exactly as before', function () {
    $subscription = Subscription::factory()->for($this->subscriber)->create();

    $this->actingAs($this->controller)->post(route('controle.scan.store'), ['code' => offlineQr($subscription)])
        ->assertOk()
        ->assertSee('data-scan-status="valid"', false);

    expect(Scan::query()->sole())
        ->source->toBe('online')
        ->client_id->toBeNull()
        ->synced_at->toBeNull();
});
