<?php

use App\Actions\Cash\CashSessionTotals;
use App\Enums\CashSessionStatus;
use App\Enums\RoleName;
use App\Models\CashSession;
use App\Models\Payment;
use App\Models\Subscriber;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

beforeEach(fn () => Carbon::setTestNow('2026-10-05 07:00:00'));

it('opens the register with the float typed with spaces', function () {
    $agent = userWithRole(RoleName::Guichetier);

    $this->actingAs($agent)->get(route('guichet.cash.show'))->assertOk()->assertSee('Ouvrir ma caisse');
    $this->actingAs($agent)->post(route('guichet.cash.open'), ['opening_float' => '25 000'])->assertRedirect(route('guichet.subscriptions.create'));

    expect(CashSession::query()->sole())->opening_float->toBe(25000)->status->toBe(CashSessionStatus::Open)
        ->and(Activity::query()->where('event', 'cash.opened')->exists())->toBeTrue();
});

it('never opens two registers for the same agent', function () {
    ['agent' => $agent] = counterSetUp();

    $this->actingAs($agent)->post(route('guichet.cash.open'), ['opening_float' => '0'])->assertSessionHasErrors(['opening_float' => 'Votre caisse est déjà ouverte.']);
});

it('counts only cash in the drawer; transfers and cancellations apart', function () {
    ['agent' => $agent, 'session' => $session, 'anani' => $anani, 'stops' => $stops] = counterSetUp();

    $this->actingAs($agent)->post(route('guichet.sales.store'), ['zone_id' => $anani->id, 'payment_method' => 'cash']);
    $subscriber = Subscriber::factory()->create(['stop_id' => $stops['Zouglou']->id]);
    $this->actingAs($agent)->post(route('guichet.subscriptions.store'), ['subscriber_id' => $subscriber->id, 'formula' => 'monthly', 'month' => '2026-10', 'payment_method' => 'wave', 'external_reference' => 'T1']);
    $this->actingAs($agent)->post(route('guichet.sales.store'), ['zone_id' => $anani->id, 'payment_method' => 'cash']);
    Payment::query()->where('receipt_number', 'TJ-2026-00002')->update(['status' => 'cancelled']);

    $totals = CashSessionTotals::for($session);
    expect($totals->cashAmount)->toBe(1000)
        ->and($totals->transferAmount)->toBe(50000)
        ->and($totals->transfersByMethod)->toBe(['Wave' => 50000])
        ->and($totals->cancelledAmount)->toBe(1000)
        ->and($totals->subscriptionCount)->toBe(1)
        ->and($totals->ticketCount)->toBe(1)
        ->and($totals->expected())->toBe(21000);
});

// -- Rule 8: no closing with an unexplained difference ---------------------------

it('closes without difference', function () {
    ['agent' => $agent, 'session' => $session, 'anani' => $anani] = counterSetUp();
    $this->actingAs($agent)->post(route('guichet.sales.store'), ['zone_id' => $anani->id, 'payment_method' => 'cash']);

    $this->actingAs($agent)->post(route('guichet.cash.close'), ['counted_amount' => '21 000'])->assertRedirect(route('guichet.cash.report', $session));

    expect($session->fresh())->status->toBe(CashSessionStatus::Closed)->expected_amount->toBe(21000)->difference->toBe(0);
});

it('refuses to close with a difference and no explanation', function (string $counted, string $message) {
    ['agent' => $agent, 'session' => $session] = counterSetUp();

    $this->actingAs($agent)->post(route('guichet.cash.close'), ['counted_amount' => $counted, 'justification' => 'oups'])
        ->assertSessionHasErrors(['justification' => $message]);

    expect($session->fresh()->isOpen())->toBeTrue();
})->with([
    'missing' => ['19500', "Il manque 500\u{00A0}FCFA. Expliquez l’écart pour pouvoir clôturer."],
    'extra' => ['20200', "Il y a 200\u{00A0}FCFA en trop. Expliquez l’écart pour pouvoir clôturer."],
]);

it('closes with a justified difference and prints the report', function () {
    ['agent' => $agent, 'session' => $session] = counterSetUp();

    $this->actingAs($agent)->post(route('guichet.cash.close'), ['counted_amount' => '19500', 'justification' => 'Erreur de monnaie rendue à 10 h'])->assertRedirect();

    expect($session->fresh())->difference->toBe(-500);
    $this->actingAs($agent)->get(route('guichet.cash.report', $session))->assertOk()->assertSee('RAPPORT DE CAISSE')->assertSee('Erreur de monnaie');
    $this->actingAs(userWithRole(RoleName::Guichetier))->get(route('guichet.cash.report', $session))->assertForbidden();
});

it('blocks sales once the register is closed', function () {
    ['agent' => $agent, 'anani' => $anani] = counterSetUp();
    $this->actingAs($agent)->post(route('guichet.cash.close'), ['counted_amount' => '20000']);

    $this->actingAs($agent)->post(route('guichet.sales.store'), ['zone_id' => $anani->id, 'payment_method' => 'cash'])->assertRedirect(route('guichet.cash.show'));

    expect(Payment::query()->count())->toBe(0);
});
