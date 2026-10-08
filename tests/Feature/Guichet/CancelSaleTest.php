<?php

use App\Actions\Cash\CashSessionTotals;
use App\Enums\PassStatus;
use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\CashSession;
use App\Models\Payment;
use App\Models\Ticket;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Carbon::setTestNow('2026-10-05 07:00:00');
    $setup = counterSetUp();
    $this->agent = $setup['agent'];
    $this->session = $setup['session'];
    $this->supervisor = userWithRole(RoleName::Gestionnaire, ['phone' => '+2250700000001']);

    $this->actingAs($this->agent)->post(route('guichet.sales.store'), ['zone_id' => $setup['anani']->id, 'payment_method' => 'cash']);
    $this->payment = Payment::query()->sole();
});

/**
 * @param  array<string, string>  $overrides
 */
function cancelSale(Payment $payment, array $overrides = []): TestResponse
{
    return test()->actingAs(test()->agent)->post(route('guichet.sales.cancel', $payment->reference), [
        'supervisor_login' => '07 00 00 00 01',
        'supervisor_password' => 'password',
        'reason' => 'Mauvaise zone choisie',
        ...$overrides,
    ]);
}

// -- Rule 9: cancellation only with a supervisor -----------------------------------

it('cancels a sale validated by a supervisor', function () {
    cancelSale($this->payment)->assertRedirect()->assertSessionHas('success');

    expect($this->payment->fresh())
        ->status->toBe(PaymentStatus::Cancelled)
        ->cancelled_by->toBe($this->supervisor->id)
        ->and(Ticket::query()->sole()->status)->toBe(PassStatus::Cancelled)
        ->and(CashSessionTotals::for($this->session)->expected())->toBe(20000)
        ->and(Activity::query()->where('event', 'sale.cancelled')->sole()->causer_id)->toBe($this->supervisor->id);
});

it('refuses without valid supervisor credentials', function (array $overrides) {
    cancelSale($this->payment, $overrides)->assertSessionHasErrorsIn('cancel_'.$this->payment->reference, 'supervisor_login');

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Succeeded);
})->with([
    'wrong password' => [['supervisor_password' => 'wrong']],
    'unknown person' => [['supervisor_login' => '0500000000']],
]);

it('refuses a person without "tickets.cancel", including the agent', function (string $who) {
    $login = match ($who) {
        'agent' => test()->agent->phone,
        'other agent' => userWithRole(RoleName::Guichetier, ['phone' => '+2250700000044'])->phone,
        'accountant' => userWithRole(RoleName::Comptable, ['phone' => '+2250700000033'])->phone,
    };

    cancelSale($this->payment, ['supervisor_login' => $login])
        ->assertSessionHasErrorsIn('cancel_'.$this->payment->reference, ['supervisor_login' => 'Identifiants du superviseur incorrects ou non autorisés.']);
})->with(['agent', 'other agent', 'accountant']);

it('refuses a disabled supervisor and blocks after 5 wrong passwords', function () {
    $this->supervisor->forceFill(['status' => UserStatus::Disabled])->save();
    cancelSale($this->payment)->assertSessionHasErrorsIn('cancel_'.$this->payment->reference, 'supervisor_login');

    $this->supervisor->forceFill(['status' => UserStatus::Active])->save();
    foreach (range(1, 5) as $attempt) {
        cancelSale($this->payment, ['supervisor_password' => 'wrong']);
    }
    cancelSale($this->payment);
    expect(session('errors')->getBag('cancel_'.$this->payment->reference)->first('supervisor_login'))->toContain('Trop d’essais');
});

it('refuses a ticket already used, a second cancellation, and another agent (IDOR)', function () {
    Ticket::query()->update(['rides_used' => 1]);
    cancelSale($this->payment)->assertSessionHasErrorsIn('cancel_'.$this->payment->reference, ['reason' => 'Ce titre a déjà servi : il ne peut plus être annulé.']);

    Ticket::query()->update(['rides_used' => 0]);
    cancelSale($this->payment);
    cancelSale($this->payment)->assertSessionHasErrorsIn('cancel_'.$this->payment->reference, 'reason');

    $other = userWithRole(RoleName::Guichetier);
    CashSession::factory()->for($other)->create();
    test()->actingAs($other)->post(route('guichet.sales.cancel', $this->payment->reference), [
        'supervisor_login' => '07 00 00 00 01', 'supervisor_password' => 'password', 'reason' => 'Tentative',
    ])->assertForbidden();
});

it('keeps what was typed (never the password) when the supervisor login fails', function () {
    test()->actingAs(test()->agent)
        ->from(route('guichet.sales.create'))
        ->followingRedirects()
        ->post(route('guichet.sales.cancel', $this->payment->reference), [
            'supervisor_login' => '07 00 00 00 01', 'supervisor_password' => 'secret-typed', 'reason' => 'Mauvaise zone choisie',
        ])
        ->assertSee('Identifiants du superviseur incorrects ou non autorisés.')
        ->assertSee('value="07 00 00 00 01"', false)
        ->assertSee('Mauvaise zone choisie')
        ->assertDontSee('secret-typed');
});

it('prints "ANNULÉ" on the receipt of a cancelled sale', function () {
    cancelSale($this->payment);

    $this->actingAs($this->agent)->get(route('guichet.sales.receipt', $this->payment->reference))
        ->assertOk()->assertSee('ANNULÉ')->assertDontSee('QR code du titre');
});
