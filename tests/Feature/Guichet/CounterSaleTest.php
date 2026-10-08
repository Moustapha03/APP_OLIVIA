<?php

use App\Enums\PassChannel;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Models\Payment;
use App\Models\Ticket;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-10-05 07:00:00');
    $setup = counterSetUp();
    $this->agent = $setup['agent'];
    $this->session = $setup['session'];
    $this->zones = ['marcory' => $setup['marcory'], 'anani' => $setup['anani'], 'bassam' => $setup['bassam']];
});

// -- Acceptance: a daily ticket for each zone, paid in cash ---------------------------

it('sells a daily ticket for each zone at its price', function (string $zone, int $price) {
    $this->actingAs($this->agent)->post(route('guichet.sales.store'), ['zone_id' => $this->zones[$zone]->id, 'payment_method' => 'cash'])
        ->assertRedirect(route('guichet.sales.create'))
        ->assertSessionHas('success');

    $payment = Payment::query()->sole();
    $ticket = Ticket::query()->sole();

    expect($payment)
        ->gateway->toBe('counter')
        ->payment_method->toBe(PaymentMethod::Cash)
        ->status->toBe(PaymentStatus::Succeeded)
        ->amount->toBe($price)
        ->receipt_number->toBe('TJ-2026-00001')
        ->cash_session_id->toBe($this->session->id)
        ->and($ticket)
        ->zone_id->toBe($this->zones[$zone]->id)
        ->price_paid->toBe($price)
        ->channel->toBe(PassChannel::Counter)
        ->and($ticket->isUsable())->toBeTrue();
})->with([['marcory', 500], ['anani', 1000], ['bassam', 1500]]);

it('prints a thermal receipt (58 and 80 mm) with the CSP-allowed print style', function (int $width) {
    $this->actingAs($this->agent)->post(route('guichet.sales.store'), ['zone_id' => $this->zones['bassam']->id, 'payment_method' => 'cash', 'print' => '1']);
    $payment = Payment::query()->sole();

    $response = $this->actingAs($this->agent)->get(route('guichet.sales.receipt', ['payment' => $payment->reference, 'largeur' => $width, 'imprimer' => 1]));

    $response->assertOk()
        ->assertSee('TICKET DU JOUR')
        ->assertSee('Reçu N° TJ-2026-00001')
        ->assertSee("size: {$width}mm auto", false)
        ->assertSee('data-auto-print', false);

    preg_match("/style-src 'self' 'nonce-([A-Za-z0-9]+)'/", (string) $response->headers->get('Content-Security-Policy'), $match);
    expect($response->getContent())->toContain('<style nonce="'.($match[1] ?? 'x').'">')->not->toContain('style="');
})->with([58, 80]);

it('validates the zone', function (mixed $zone) {
    $this->actingAs($this->agent)->post(route('guichet.sales.store'), ['zone_id' => $zone, 'payment_method' => 'cash'])->assertSessionHasErrors('zone_id');

    expect(Payment::query()->count())->toBe(0);
})->with(['', 999999]);

it('refuses an inactive zone', function () {
    $this->zones['anani']->update(['is_active' => false]);

    $this->actingAs($this->agent)->post(route('guichet.sales.store'), ['zone_id' => $this->zones['anani']->id, 'payment_method' => 'cash'])->assertSessionHasErrors('zone_id');
});

it('never shows another agent\'s receipt (IDOR)', function () {
    $this->actingAs($this->agent)->post(route('guichet.sales.store'), ['zone_id' => $this->zones['anani']->id, 'payment_method' => 'cash']);

    $this->actingAs(userWithRole(RoleName::Guichetier))->get(route('guichet.sales.receipt', Payment::query()->sole()->reference))->assertForbidden();
});

it('shows the zones with their daily price', function () {
    $this->actingAs($this->agent)->get(route('guichet.sales.create'))
        ->assertOk()
        ->assertSee('Zone du client')
        ->assertSee('Koumassi / Marcory')
        ->assertSee("500\u{00A0}FCFA")
        ->assertSee('Espèces');
});
