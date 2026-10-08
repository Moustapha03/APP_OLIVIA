<?php

use App\Enums\FareType;
use App\Enums\PassChannel;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Jobs\ProcessPaymentNotification;
use App\Models\Fare;
use App\Models\Payment;
use App\Models\Stop;
use App\Models\Subscriber;
use App\Models\Subscription;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Payments\FakePaymentGateway;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Carbon::setTestNow('2026-10-05 08:00:00');
    $setup = networkSetUp();
    $this->stops = $setup['stops'];
    $this->zones = ['marcory' => $setup['marcory'], 'anani' => $setup['anani'], 'bassam' => $setup['bassam']];

    $this->customer = userWithRole(RoleName::Client);
    $this->subscriber = Subscriber::factory()->create(['user_id' => $this->customer->id, 'full_name' => 'Awa Koné', 'stop_id' => $this->stops['Zouglou']->id]);
});

/**
 * Posts the purchase form and returns the created payment.
 *
 * @param  array<string, mixed>  $extra
 */
function startPurchase(User $customer, Stop $stop, string $type, array $extra = []): Payment
{
    test()->actingAs($customer)
        ->post(route('espace.purchase.store'), ['stop_id' => $stop->id, 'type' => $type, ...$extra])
        ->assertRedirectContains('/paiement-test/');

    return Payment::query()->latest('id')->firstOrFail();
}

/**
 * The customer validates on the simulated Mobile Money page, then comes back.
 */
function payOnFakePage(User $customer, Payment $payment, bool $accept = true): void
{
    app(FakePaymentGateway::class)->record($payment, $accept);
    test()->actingAs($customer)->get(route('espace.payments.show', $payment->reference));
}

// -- Daily tickets online ----------------------------------------------------------------

it('sells a daily ticket at the price of the boarding point zone', function (string $stop, int $price) {
    $payment = startPurchase($this->customer, $this->stops[$stop], 'daily');

    expect($payment)->status->toBe(PaymentStatus::Pending)->amount->toBe($price)->payment_method->toBe(PaymentMethod::Online)
        ->and(Ticket::query()->count())->toBe(0); // nothing before payment (rule 5)

    payOnFakePage($this->customer, $payment);

    $ticket = Ticket::query()->sole();
    expect($payment->fresh())->status->toBe(PaymentStatus::Succeeded)->receipt_number->toBe('TJ-2026-00001')
        ->and($ticket)->price_paid->toBe($price)->channel->toBe(PassChannel::Online)->zone_id->toBe($this->stops[$stop]->zone_id)
        ->and($ticket->isUsable())->toBeTrue();
})->with([['Marcory', 500], ['Carrefour Aéroport', 1000], ['Zouglou', 1500]]);

it('lands on the ticket with its QR code after payment', function () {
    $payment = startPurchase($this->customer, $this->stops['Bonoua'], 'daily');
    app(FakePaymentGateway::class)->record($payment, true);

    $this->actingAs($this->customer)->get(route('espace.payments.show', $payment->reference))
        ->assertRedirect(route('espace.tickets.show', Ticket::query()->sole()->uuid));

    $this->actingAs($this->customer)->get(route('espace.tickets.show', Ticket::query()->sole()->uuid))
        ->assertOk()->assertSee('Montrez ce code au contrôleur');
});

it('never reads the price from the form', function () {
    expect(startPurchase($this->customer, $this->stops['Bonoua'], 'daily', ['amount' => 1, 'fare_id' => 999])->amount)->toBe(1500);
});

it('keeps the price of the purchase day if the fare changes before payment', function () {
    $payment = startPurchase($this->customer, $this->stops['Marcory'], 'daily');

    Carbon::setTestNow('2026-10-06 08:00:00');
    Fare::query()->where('zone_id', $this->zones['marcory']->id)->ofType(FareType::Daily)->update(['effective_to' => '2026-10-05']);
    Fare::factory()->for($this->zones['marcory'])->create(['amount' => 600, 'effective_from' => '2026-10-06']);

    payOnFakePage($this->customer, $payment);

    expect(Ticket::query()->sole()->price_paid)->toBe(500);
});

it('issues nothing when the payment is refused, or when the customer comes back without paying', function () {
    $refused = startPurchase($this->customer, $this->stops['Anani'], 'daily');
    payOnFakePage($this->customer, $refused, accept: false);
    expect($refused->fresh()->status)->toBe(PaymentStatus::Failed);

    $waiting = startPurchase($this->customer, $this->stops['Anani'], 'daily');
    $this->actingAs($this->customer)->get(route('espace.payments.show', $waiting->reference))
        ->assertOk()->assertSee('Vérification du paiement')->assertSee('http-equiv="refresh"', false);

    expect(Ticket::query()->count())->toBe(0);
});

// -- Monthly subscriptions online --------------------------------------------------------

it('sells a calendar-month subscription for one of the account subscribers', function (string $formula, int $price) {
    $payment = startPurchase($this->customer, $this->stops['Zouglou'], $formula, ['subscriber_id' => $this->subscriber->id, 'month' => '2026-10']);
    payOnFakePage($this->customer, $payment);

    $subscription = Subscription::query()->sole();
    expect($payment->fresh()->receipt_number)->toBe('BA-2026-0001')
        ->and($subscription)
        ->subscriber_id->toBe($this->subscriber->id)
        ->user_id->toBe($this->customer->id)
        ->formula->toBe(FareType::from($formula))
        ->price_paid->toBe($price)
        ->and($subscription->starts_on->toDateString())->toBe('2026-10-01')
        ->and($subscription->ends_on->toDateString())->toBe('2026-10-31');
})->with([['monthly', 50000], ['monthly_morning', 30000], ['monthly_evening', 30000]]);

it('sells next month in advance', function () {
    payOnFakePage($this->customer, startPurchase($this->customer, $this->stops['Zouglou'], 'monthly', ['subscriber_id' => $this->subscriber->id, 'month' => '2026-11']));

    expect(Subscription::query()->sole()->starts_on->toDateString())->toBe('2026-11-01');
});

it('refuses a subscription for someone else\'s subscriber, without photo, or twice for the same month', function () {
    $stranger = Subscriber::factory()->create(['user_id' => userWithRole(RoleName::Client)->id]);
    $this->actingAs($this->customer)->post(route('espace.purchase.store'), ['stop_id' => $this->stops['Zouglou']->id, 'type' => 'monthly', 'subscriber_id' => $stranger->id, 'month' => '2026-10'])
        ->assertSessionHasErrors('subscriber_id');

    $noPhoto = Subscriber::factory()->withoutPhoto()->create(['user_id' => $this->customer->id]);
    $this->actingAs($this->customer)->post(route('espace.purchase.store'), ['stop_id' => $this->stops['Zouglou']->id, 'type' => 'monthly', 'subscriber_id' => $noPhoto->id, 'month' => '2026-10'])
        ->assertSessionHasErrors('photo');

    payOnFakePage($this->customer, startPurchase($this->customer, $this->stops['Zouglou'], 'monthly', ['subscriber_id' => $this->subscriber->id, 'month' => '2026-10']));
    $this->actingAs($this->customer)->post(route('espace.purchase.store'), ['stop_id' => $this->stops['Zouglou']->id, 'type' => 'monthly_evening', 'subscriber_id' => $this->subscriber->id, 'month' => '2026-10'])
        ->assertSessionHasErrors('month');

    expect(Subscription::query()->count())->toBe(1);
});

it('requires the subscriber and month for a subscription', function () {
    $this->actingAs($this->customer)->post(route('espace.purchase.store'), ['stop_id' => $this->stops['Zouglou']->id, 'type' => 'monthly'])
        ->assertSessionHasErrors(['subscriber_id', 'month']);
});

it('does not change a running subscription when the fare changes (rule 6)', function () {
    payOnFakePage($this->customer, startPurchase($this->customer, $this->stops['Zouglou'], 'monthly', ['subscriber_id' => $this->subscriber->id, 'month' => '2026-10']));

    Fare::query()->where('zone_id', $this->zones['bassam']->id)->ofType(FareType::Monthly)->update(['effective_to' => '2026-10-09']);
    Fare::factory()->for($this->zones['bassam'])->monthly(55000)->create(['effective_from' => '2026-10-10']);
    Carbon::setTestNow('2026-10-20 08:00:00');

    expect(Subscription::query()->sole())->price_paid->toBe(50000)->and(Subscription::query()->sole()->isActiveOn())->toBeTrue();
});

// -- Screens and protections ---------------------------------------------------------------

it('shows boarding points by zone, then formulas with prices, subscribers and months', function () {
    $this->actingAs($this->customer)->get(route('espace.purchase.create'))
        ->assertOk()->assertSee('Où montez-vous ?')->assertSee('Zouglou')->assertSee('Bonoua / Bassam');

    $this->actingAs($this->customer)->get(route('espace.purchase.formula', $this->stops['Zouglou']))
        ->assertOk()->assertSee('Ticket du jour')->assertSee('Matin simple')->assertSee('Awa Koné')->assertSee('Octobre 2026')->assertSee("50\u{00A0}000\u{00A0}FCFA");
});

it('rate limits purchases: 10 per minute', function () {
    foreach (range(1, 10) as $i) {
        $this->actingAs($this->customer)->post(route('espace.purchase.store'), ['stop_id' => $this->stops['Anani']->id, 'type' => 'daily'])->assertStatus(302);
    }

    $this->actingAs($this->customer)->post(route('espace.purchase.store'), ['stop_id' => $this->stops['Anani']->id, 'type' => 'daily'])->assertStatus(429);
});

it('runs the simulated Mobile Money page, signed and for the owner only', function () {
    Queue::fake();

    $response = $this->actingAs($this->customer)->post(route('espace.purchase.store'), ['stop_id' => $this->stops['Anani']->id, 'type' => 'daily']);
    $fakeUrl = (string) $response->headers->get('Location');
    $payment = Payment::query()->sole();

    $this->actingAs(userWithRole(RoleName::Client))->get($fakeUrl)->assertForbidden();
    $this->actingAs($this->customer)->get(route('payments.fake.show', $payment->reference))->assertForbidden(); // unsigned
    $this->actingAs($this->customer)->get($fakeUrl)->assertOk()->assertSee('Simulation de paiement');
    $this->actingAs($this->customer)->post($fakeUrl, ['decision' => 'accept'])->assertRedirect(route('espace.payments.show', $payment->reference));

    Queue::assertPushed(ProcessPaymentNotification::class);
});
