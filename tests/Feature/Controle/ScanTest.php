<?php

use App\Enums\FareType;
use App\Enums\PassStatus;
use App\Enums\Period;
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
use Illuminate\Testing\TestResponse;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Carbon::setTestNow('2026-10-06 07:30:00');
    $this->network = networkSetUp();
    $this->controller = userWithRole(RoleName::Controleur, ['first_name' => 'Konan', 'last_name' => 'Yao']);
    $this->subscriber = Subscriber::factory()->create(['full_name' => 'BROU NESTOR', 'stop_id' => $this->network['stops']['Zouglou']->id]);
});

/**
 * @param  array<string, mixed>  $attributes
 */
function scanSubscription(array $attributes = []): Subscription
{
    return Subscription::factory()->for(test()->subscriber)->create($attributes);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function scanTicket(array $attributes = []): Ticket
{
    return Ticket::factory()->create([
        'user_id' => null,
        'zone_id' => test()->network['anani']->id,
        'stop_id' => test()->network['stops']['Anani']->id,
        ...$attributes,
    ]);
}

function qr(Ticket|Subscription $pass): string
{
    return app(PassSigner::class)->sign($pass);
}

function scanCode(string $code, ?User $as = null): TestResponse
{
    return test()->actingAs($as ?? test()->controller)->post(route('controle.scan.store'), ['code' => $code]);
}

// -- Subscriptions ----------------------------------------------------------------

it('accepts a subscription of the month and shows the holder with photo', function () {
    $subscription = scanSubscription();

    scanCode(qr($subscription))
        ->assertOk()
        ->assertSee('data-scan-status="valid"', false)
        ->assertSee('VALIDE')
        ->assertSee('BROU NESTOR')
        ->assertSee('/photos/abonnes/'.$this->subscriber->id.'?expires=', false)
        ->assertSee('Abonnement Matin &amp; soir', false)
        ->assertSee('Bonoua / Bassam')
        ->assertSee('Zouglou')
        ->assertSee('octobre 2026')
        ->assertSee('Scanner le suivant');

    expect(Scan::query()->sole())
        ->user_id->toBe($this->controller->id)
        ->kind->toBe('subscription')
        ->subscription_id->toBe($subscription->id)
        ->result->toBe(ScanResult::Valid)
        ->reason_code->toBe('ok')
        ->period->toBe(Period::Morning)
        ->and(Scan::query()->sole()->scanned_at->toDateTimeString())->toBe('2026-10-06 07:30:00');
});

it('refuses a "simple" subscription outside its period', function (FareType $formula, string $time, string $reason) {
    Carbon::setTestNow("2026-10-06 {$time}");
    $subscription = scanSubscription(['formula' => $formula]);

    scanCode(qr($subscription))
        ->assertOk()
        ->assertSee('data-scan-status="refused"', false)
        ->assertSee($reason);

    expect(Scan::query()->sole())->result->toBe(ScanResult::Refused)->reason_code->toBe('wrong_period');
})->with([
    'matin, le soir' => [FareType::MonthlyMorning, '18:15:00', 'Abonnement Matin simple : non valable le soir'],
    'soir, le matin' => [FareType::MonthlyEvening, '06:45:00', 'Abonnement Soir simple : non valable le matin'],
]);

it('accepts a "simple" subscription in its period, the evening starting at the configured time', function () {
    config(['transport.scans.evening_starts_at' => '14:00']);
    Carbon::setTestNow('2026-10-06 13:30:00');
    $morning = scanSubscription(['formula' => FareType::MonthlyMorning]);

    scanCode(qr($morning))->assertSee('data-scan-status="valid"', false);

    Carbon::setTestNow('2026-10-06 14:00:00');
    $evening = scanSubscription(['formula' => FareType::MonthlyEvening]);

    scanCode(qr($evening))->assertSee('data-scan-status="valid"', false);
    expect(Scan::query()->pluck('period')->all())->toBe([Period::Morning, Period::Evening]);
});

it('refuses an expired subscription', function () {
    $subscription = scanSubscription(['starts_on' => '2026-09-01', 'ends_on' => '2026-09-30']);

    scanCode(qr($subscription))
        ->assertSee('data-scan-status="refused"', false)
        ->assertSee('Abonnement de septembre 2026 : expiré');
});

it('refuses a subscription of a later month', function () {
    $subscription = scanSubscription(['starts_on' => '2026-11-01', 'ends_on' => '2026-11-30']);

    scanCode(qr($subscription))
        ->assertSee('data-scan-status="refused"', false)
        ->assertSee('Abonnement de novembre 2026 : pas encore commencé');
});

it('refuses cancelled and refunded passes', function () {
    scanCode(qr(scanSubscription(['status' => PassStatus::Cancelled])))
        ->assertSee('data-scan-status="refused"', false)
        ->assertSee('Abonnement annulé');

    scanCode(qr(scanTicket(['status' => PassStatus::Refunded])))
        ->assertSee('data-scan-status="refused"', false)
        ->assertSee('Ticket remboursé');

    expect(Scan::query()->pluck('reason_code')->all())->toBe(['cancelled', 'refunded']);
});

it('warns when the subscriber has no photo', function () {
    $this->subscriber->forceFill(['photo_path' => null])->save();

    scanCode(qr(scanSubscription()))
        ->assertSee('data-scan-status="warning"', false)
        ->assertSee('Photo manquante : vérifiez une pièce d’identité')
        ->assertSee('BROU NESTOR')
        ->assertDontSee('/photos/abonnes/', false);

    expect(Scan::query()->sole()->reason_code)->toBe('photo_missing');
});

// -- Unknown codes ----------------------------------------------------------------

it('refuses forged or unknown codes and keeps a trace', function (string $code) {
    scanCode($code)
        ->assertOk()
        ->assertSee('data-scan-status="refused"', false)
        ->assertSee('Code inconnu ou falsifié');

    expect(Scan::query()->sole())
        ->kind->toBe('unknown')
        ->ticket_id->toBeNull()
        ->subscription_id->toBeNull()
        ->result->toBe(ScanResult::Refused)
        ->and(Activity::query()->where('log_name', 'scans')->where('event', 'scan.unknown')->count())->toBe(1);
})->with([
    'altered signature' => fn () => substr(qr(scanSubscription()), 0, -2).'xx',
    'unknown pass' => fn () => app(PassSigner::class)->sign(Subscription::factory()->make(['uuid' => '0b2f9c1e-4d6a-4c3b-9e2f-7a1b2c3d4e5f'])),
    'not one of ours' => ['https://example.com/promo'],
]);

// -- Receipt number typed by hand -------------------------------------------------

it('finds a subscription from its receipt number, whatever the case or spacing, and asks to check the holder', function (string $typed) {
    $subscription = scanSubscription();
    $payment = Payment::factory()->monthly()->succeeded()->create(['receipt_number' => 'BA-2026-0017', 'zone_id' => $subscription->zone_id]);
    $payment->payable()->associate($subscription)->save();
    $subscription->forceFill(['payment_id' => $payment->id])->save();

    scanCode($typed)
        ->assertSee('data-scan-status="warning"', false)
        ->assertSee('N° saisi à la main : vérifiez le nom et la photo')
        ->assertSee('BROU NESTOR')
        ->assertSee('/photos/abonnes/'.$this->subscriber->id.'?expires=', false)
        ->assertSee('BA-2026-0017');

    expect(Scan::query()->sole()->reason_code)->toBe('ok');
})->with(['BA-2026-0017', 'ba-2026-0017', ' ba 2026 17 ']);

it('says when a receipt number does not exist', function () {
    scanCode('TJ-2026-00999')
        ->assertSee('data-scan-status="refused"', false)
        ->assertSee('Reçu TJ-2026-00999 introuvable');
});

it('requires a code', function () {
    $this->actingAs($this->controller)
        ->from(route('controle.scan'))
        ->post(route('controle.scan.store'), ['code' => ''])
        ->assertRedirect(route('controle.scan'))
        ->assertSessionHasErrors('code');

    expect(Scan::query()->count())->toBe(0);
});

// -- Daily tickets ----------------------------------------------------------------

it('counts one ride per scan, then refuses a used-up ticket', function () {
    $ticket = scanTicket(['rides_allowed' => 2]);

    scanCode(qr($ticket))->assertSee('data-scan-status="valid"', false)->assertSee('Trajet 1 sur 2');
    expect($ticket->fresh()->rides_used)->toBe(1);

    Carbon::setTestNow('2026-10-06 17:30:00');
    scanCode(qr($ticket))->assertSee('data-scan-status="valid"', false)->assertSee('Trajet 2 sur 2');

    Carbon::setTestNow('2026-10-06 18:30:00');
    scanCode(qr($ticket))
        ->assertSee('data-scan-status="refused"', false)
        ->assertSee('Ticket déjà utilisé (2/2 trajets)');

    expect($ticket->fresh()->rides_used)->toBe(2)
        ->and(Scan::query()->pluck('reason_code')->all())->toBe(['ok', 'ok', 'used_up']);
});

it('refuses a ticket of another day', function () {
    scanCode(qr(scanTicket(['valid_on' => '2026-10-05'])))
        ->assertSee('data-scan-status="refused"', false)
        ->assertSee('Ticket du 5 octobre 2026 : expiré');

    scanCode(qr(scanTicket(['valid_on' => '2026-10-07'])))
        ->assertSee('Ticket du 7 octobre 2026 : pas encore valable');
});

// -- Anti-replay ------------------------------------------------------------------

it('warns when the same pass was accepted a moment ago, by any controller', function () {
    $subscription = scanSubscription();
    $colleague = userWithRole(RoleName::Controleur, ['first_name' => 'Ama', 'last_name' => 'Kouadio']);

    scanCode(qr($subscription), $colleague)->assertSee('data-scan-status="valid"', false);

    Carbon::setTestNow('2026-10-06 07:42:00');
    scanCode(qr($subscription))
        ->assertSee('data-scan-status="warning"', false)
        ->assertSee('Déjà scanné à 07:30 par Ama Kouadio');

    scanCode(qr($subscription))->assertSee('Déjà scanné à 07:30 par Ama Kouadio');

    // Outside the window (30 min by default): accepted again.
    Carbon::setTestNow('2026-10-06 08:01:00');
    scanCode(qr($subscription))->assertSee('data-scan-status="valid"', false);
});

it('does not count a ride again on a replayed ticket', function () {
    $ticket = scanTicket();

    scanCode(qr($ticket));
    Carbon::setTestNow('2026-10-06 07:35:00');
    scanCode(qr($ticket))
        ->assertSee('data-scan-status="warning"', false)
        ->assertSee('Déjà scanné à 07:30 par vous');

    expect($ticket->fresh()->rides_used)->toBe(1);
});

// -- History ----------------------------------------------------------------------

it('lists the controller’s own scans of the day', function () {
    scanCode(qr(scanSubscription()));
    scanCode('https://example.com/promo');
    scanCode(qr(scanTicket()), userWithRole(RoleName::Controleur));

    Scan::query()->first()?->forceFill(['scanned_at' => now()->subDay()])->save();

    $this->actingAs($this->controller)->get(route('controle.scans.index'))
        ->assertOk()
        ->assertSee('Code inconnu ou falsifié')
        ->assertDontSee('BROU NESTOR')
        ->assertDontSee('Trajet 1 sur 2')
        ->assertSee('1 scan');
});

// -- Access -----------------------------------------------------------------------

it('is reserved to controllers', function (RoleName $role) {
    $subscription = scanSubscription();

    scanCode(qr($subscription), userWithRole($role))->assertForbidden();
    $this->get(route('controle.scans.index'))->assertForbidden();

    expect(Scan::query()->count())->toBe(0);
})->with([RoleName::Client, RoleName::Guichetier, RoleName::Comptable]);

it('redirects guests to the login page', function () {
    $this->post(route('controle.scan.store'), ['code' => 'BA-2026-0017'])->assertRedirect(route('login'));
    $this->get(route('controle.scans.index'))->assertRedirect(route('login'));
    $this->get(route('controle.scan'))->assertRedirect(route('login'));
});

it('shows the camera scanner with a receipt number fallback', function () {
    $this->actingAs($this->controller)->get(route('controle.scan'))
        ->assertOk()
        ->assertSee('x-data="scanner"', false)
        ->assertSee('N° de reçu')
        ->assertSee(route('controle.scan.store'))
        ->assertDontSee('Le scanner arrive bientôt');
});
