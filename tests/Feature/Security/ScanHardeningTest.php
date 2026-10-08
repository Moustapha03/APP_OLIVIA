<?php

use App\Actions\Scans\VerifyPass;
use App\Enums\RoleName;
use App\Enums\ScanResult;
use App\Enums\UserStatus;
use App\Models\Payment;
use App\Models\Scan;
use App\Models\Subscriber;
use App\Models\Subscription;
use App\Models\Ticket;
use App\Models\User;
use App\Policies\SubscriberPolicy;
use App\Services\Photos\SubscriberPhoto;
use App\Services\Qr\PassSigner;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Spatie\Activitylog\Models\Activity;

/*
 * Audit findings on the control (step 7): typed receipt numbers (F3),
 * photo enumeration (F10), back-dated offline scans (F14), offline copies
 * of another account (F15), content of the list of the day (F16).
 */

beforeEach(function () {
    Carbon::setTestNow('2026-10-06 07:30:00');
    $this->network = networkSetUp();
    $this->controller = userWithRole(RoleName::Controleur);
    $this->subscriber = Subscriber::factory()->create(['full_name' => 'BROU NESTOR', 'stop_id' => $this->network['stops']['Zouglou']->id]);
});

/**
 * @param  array<string, mixed>  $attributes
 */
function hardeningTicket(array $attributes = [], ?string $receipt = null): Ticket
{
    $ticket = Ticket::factory()->create([
        'user_id' => null,
        'zone_id' => test()->network['anani']->id,
        'stop_id' => test()->network['stops']['Anani']->id,
        ...$attributes,
    ]);

    if ($receipt !== null) {
        $payment = Payment::factory()->succeeded()->create(['receipt_number' => $receipt, 'zone_id' => $ticket->zone_id]);
        $payment->payable()->associate($ticket)->save();
        $ticket->forceFill(['payment_id' => $payment->id])->save();
    }

    return $ticket;
}

function hardeningSubscription(string $receipt = 'BA-2026-0017'): Subscription
{
    $subscription = Subscription::factory()->for(test()->subscriber)->create();
    $payment = Payment::factory()->monthly()->succeeded()->create(['receipt_number' => $receipt, 'zone_id' => $subscription->zone_id]);
    $payment->payable()->associate($subscription)->save();
    $subscription->forceFill(['payment_id' => $payment->id])->save();

    return $subscription;
}

function hardeningScan(string $code, ?User $as = null): TestResponse
{
    return test()->actingAs($as ?? test()->controller)->post(route('controle.scan.store'), ['code' => $code]);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function hardeningItem(string $code, array $overrides = []): array
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
function hardeningSync(array $items): TestResponse
{
    return test()->actingAs(test()->controller)->postJson(route('api.controle.scans.sync'), ['scans' => $items]);
}

// -- F3: receipt number typed by hand ---------------------------------------------------

it('refuses a daily ticket number typed by hand, without counting a ride, and traces it', function (string $typed) {
    $ticket = hardeningTicket(receipt: 'TJ-2026-00042');

    hardeningScan($typed)
        ->assertOk()
        ->assertSee('data-scan-status="refused"', false)
        ->assertSee('Ticket du jour : scannez le QR code du reçu (saisie du numéro non acceptée)');

    expect(Scan::query()->sole())
        ->kind->toBe('ticket')
        ->ticket_id->toBe($ticket->id)
        ->result->toBe(ScanResult::Refused)
        ->reason_code->toBe('manual_entry')
        ->and($ticket->fresh()->rides_used)->toBe(0)
        ->and(Activity::query()->where('event', 'scan.manual_entry')->sole())
        ->log_name->toBe('scans')
        ->causer_id->toBe($this->controller->id);

    // Its QR code is still accepted.
    hardeningScan(app(PassSigner::class)->sign($ticket))->assertSee('data-scan-status="valid"', false);
    expect($ticket->fresh()->rides_used)->toBe(1)
        ->and(Activity::query()->where('event', 'scan.manual_entry')->count())->toBe(1);
})->with(['TJ-2026-00042', 'tj 2026 42']);

it('accepts a typed subscription number as a warning (name and photo to check), traced', function () {
    hardeningSubscription();

    hardeningScan('ba-2026-17')
        ->assertSee('data-scan-status="warning"', false)
        ->assertSee('N° saisi à la main : vérifiez le nom et la photo')
        ->assertSee('BROU NESTOR');

    $scan = Scan::query()->sole();
    expect($scan->reason_code)->toBe('ok')
        ->and(Activity::query()->where('event', 'scan.manual_entry')->sole()->getExtraProperty('receipt_number'))->toBe('BA-2026-0017')
        ->and(Activity::query()->where('event', 'scan.manual_entry')->sole()->subject_id)->toBe($scan->id);
});

it('does not mark scanned QR codes as manual entries', function () {
    hardeningScan(app(PassSigner::class)->sign(hardeningSubscription()))->assertSee('data-scan-status="valid"', false);

    expect(Activity::query()->where('event', 'scan.manual_entry')->count())->toBe(0);
});

it('refuses a ticket number typed offline, even if an old phone accepted it', function () {
    $ticket = hardeningTicket(receipt: 'TJ-2026-00042');

    hardeningSync([
        hardeningItem('TJ-2026-00042'),
        hardeningItem('TJ-2026-00042', ['result' => 'refused', 'reason_code' => 'manual_entry', 'scanned_at' => '2026-10-06T07:20:00Z']),
    ])->assertOk()
        ->assertJsonPath('results.0.result', 'refused')
        ->assertJsonPath('results.0.reason_code', 'manual_entry')
        ->assertJsonPath('results.1.reason_code', 'manual_entry');

    expect($ticket->fresh()->rides_used)->toBe(0)
        ->and(Scan::query()->pluck('source')->unique()->all())->toBe(['offline'])
        ->and(Activity::query()->where('event', 'scan.manual_entry')->count())->toBe(2);
});

it('tells the controller that typed numbers are for subscriptions only', function () {
    $this->actingAs($this->controller)->get(route('controle.scan'))
        ->assertOk()
        ->assertSee('Abonnements seulement')
        ->assertSee('Ticket du jour : scannez son QR code, le numéro n’est pas accepté.')
        ->assertDontSee('ticket : TJ-2026-00001');
});

// -- F10: photos ------------------------------------------------------------------------

it('lets a controller see the photo of the pass just scanned only, for 5 minutes', function () {
    Storage::disk(SubscriberPhoto::DISK)->put($this->subscriber->photo_path, 'jpeg');
    $other = Subscriber::factory()->create();
    Storage::disk(SubscriberPhoto::DISK)->put($other->photo_path, 'jpeg');

    $html = hardeningScan(app(PassSigner::class)->sign(hardeningSubscription()))->assertOk()->getContent();
    preg_match('#src="(/photos/abonnes/'.$this->subscriber->id.'\?[^"]+)"#', (string) $html, $m);
    expect($m)->not->toBeEmpty();
    $url = html_entity_decode($m[1]);

    $this->actingAs($this->controller)->get($url)->assertOk()->assertHeader('Content-Type', 'image/jpeg');

    // Ids cannot be iterated: unsigned, or the signature of another subscriber.
    $this->actingAs($this->controller)->get(route('photos.subscriber', $other))->assertForbidden();
    $this->actingAs($this->controller)->get(str_replace('/abonnes/'.$this->subscriber->id.'?', '/abonnes/'.$other->id.'?', $url))->assertForbidden();

    // Guests still go to the login page.
    auth()->logout();
    $this->get($url)->assertRedirect(route('login'));

    Carbon::setTestNow(now()->addMinutes(6));
    $this->actingAs($this->controller)->get($url)->assertForbidden();
});

it('no longer grants photos to users.view or scans.create alone', function () {
    $policy = new SubscriberPolicy;

    expect($policy->viewPhoto($this->controller, $this->subscriber))->toBeFalse()
        ->and($policy->viewPhoto(userWithRole(RoleName::Direction), $this->subscriber))->toBeFalse()
        ->and($policy->viewPhoto(userWithRole(RoleName::Guichetier), $this->subscriber))->toBeTrue()
        ->and($policy->viewPhoto(userWithRole(RoleName::Gestionnaire), $this->subscriber))->toBeTrue();
});

it('still shows photos on the desk and admin pages', function () {
    $this->actingAs(userWithRole(RoleName::Guichetier))->get(route('guichet.subscribers.index', ['q' => 'BROU']))
        ->assertOk()->assertSee(route('photos.subscriber', $this->subscriber));

    $this->actingAs(userWithRole(RoleName::Gestionnaire))->get(route('admin.subscribers.show', $this->subscriber))
        ->assertOk()->assertSee(route('photos.subscriber', $this->subscriber));
});

it('makes signed photo links for the verdict', function () {
    expect(VerifyPass::photoUrl(12))->toStartWith('/photos/abonnes/12?expires=')->toContain('&signature=');
});

// -- F14: back-dated offline scans ------------------------------------------------------

it('accepts offline scans up to 24 hours old and 10 minutes ahead', function (string $at, bool $accepted) {
    Carbon::setTestNow('2026-10-06 12:00:00');
    $response = hardeningSync([hardeningItem('BA-2026-9999', ['result' => 'refused', 'reason_code' => 'unknown', 'scanned_at' => $at])]);

    $accepted
        ? $response->assertOk()->assertJsonPath('created', 1)
        : $response->assertUnprocessable()->assertJsonValidationErrors('scans.0.scanned_at');
})->with([
    '23 h old' => ['2026-10-05T13:00:00Z', true],
    '25 h old' => ['2026-10-05T11:00:00Z', false],
    '9 min ahead' => ['2026-10-06T12:09:00Z', true],
    '11 min ahead' => ['2026-10-06T12:11:00Z', false],
]);

it('counts no ride on a ticket when the offline scan arrives after the ticket day', function () {
    Carbon::setTestNow('2026-10-06 06:00:00');
    $ticket = hardeningTicket(['valid_on' => '2026-10-05', 'rides_allowed' => 2]);
    $items = [hardeningItem(app(PassSigner::class)->sign($ticket), ['scanned_at' => '2026-10-05T18:00:00Z', 'period' => 'evening'])];

    hardeningSync($items)->assertOk()
        ->assertJsonPath('results.0.status', 'created')
        ->assertJsonPath('results.0.reason_code', 'offline_late');

    expect($ticket->fresh()->rides_used)->toBe(0)
        ->and(Scan::query()->sole())
        ->ticket_id->toBe($ticket->id)
        ->result->toBe(ScanResult::Warning)
        ->and(Scan::query()->sole()->scanned_at->toDateTimeString())->toBe('2026-10-05 18:00:00');

    // Still idempotent.
    hardeningSync($items)->assertOk()->assertJsonPath('results.0.status', 'duplicate');
    expect(Scan::query()->count())->toBe(1);
});

it('refuses an offline scan dated before the ticket day', function () {
    $ticket = hardeningTicket(['valid_on' => '2026-10-06']);

    hardeningSync([hardeningItem(app(PassSigner::class)->sign($ticket), ['scanned_at' => '2026-10-05T20:00:00Z', 'period' => 'evening'])])
        ->assertOk()->assertJsonPath('results.0.result', 'refused')->assertJsonPath('results.0.reason_code', 'not_started');

    expect($ticket->fresh()->rides_used)->toBe(0);
});

it('still counts a ride for an offline scan synced on the ticket day', function () {
    $ticket = hardeningTicket();

    hardeningSync([hardeningItem(app(PassSigner::class)->sign($ticket))])->assertOk()->assertJsonPath('results.0.reason_code', 'ok');

    expect($ticket->fresh()->rides_used)->toBe(1);
});

it('drops offline scans older than 24 hours on the phone and says so', function () {
    $offline = (string) file_get_contents(resource_path('js/controle/offline.js'));
    $scanner = (string) file_get_contents(resource_path('js/alpine/scanner.js'));

    expect($offline)->toContain('export const MAX_AGE_MS = 24 * 60 * 60 * 1000;')
        ->toContain('export async function dropStale(')
        ->toContain("reasonCode: 'manual_entry'")
        ->and($scanner)->toContain('get droppedLabel()');

    $this->actingAs($this->controller)->get(route('controle.scan'))->assertSee('data-dropped-label', false);
});

// -- F15: offline copies and account changes --------------------------------------------

it('exposes the account on every page, except the cached offline page', function () {
    expect((string) $this->get(route('login'))->getContent())->toMatch('#<body[^>]*data-user-id=""#');

    $html = (string) $this->actingAs($this->controller)->get(route('controle.scan'))->getContent();
    expect($html)->toMatch('#<body[^>]*data-user-id="'.$this->controller->id.'"#');

    $offline = (string) $this->get(route('offline'))->getContent();
    expect($offline)->not->toMatch('#<body[^>]*data-user-id#');
});

it('erases the offline copies when the account changes', function () {
    $pwa = (string) file_get_contents(resource_path('js/pwa.js'));
    $sw = (string) file_get_contents(public_path('sw.js'));

    expect($pwa)->toContain('document.body?.dataset.userId')
        ->toContain("postMessage({ type: 'clear' })")
        ->toContain("const OFFLINE_CONTROL_DB = 'controle-hors-ligne';")
        ->and($sw)->toContain("const VERSION = 'v2';");
});

it('asks the browser to erase stored data when a disabled account is logged out', function () {
    $user = userWithRole(RoleName::Controleur);
    $user->forceFill(['status' => UserStatus::Disabled])->save();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertRedirect(route('login'))
        ->assertHeader('Clear-Site-Data', '"storage"');

    $this->actingAs($user)->getJson(route('api.controle.manifest'))
        ->assertUnauthorized()
        ->assertHeader('Clear-Site-Data', '"storage"');
});

// -- F16: list of the day ---------------------------------------------------------------

it('keeps the list of the day to what an offline check needs', function () {
    hardeningSubscription();
    hardeningTicket(receipt: 'TJ-2026-00042');

    $passes = collect($this->actingAs($this->controller)->getJson(route('api.controle.manifest'))
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->json('passes'))->keyBy('kind');

    expect($passes['s']['receipt_number'])->toBe('BA-2026-0017')
        ->and($passes['s']['holder'])->toBe('BROU NESTOR')
        ->and($passes['t']['receipt_number'])->toBeNull()
        ->and($passes['t']['holder'])->toBeNull();
});
