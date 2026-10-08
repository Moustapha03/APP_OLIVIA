<?php

use App\Actions\Payments\ConfirmPayment;
use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Models\Fare;
use App\Models\Payment;
use App\Models\Subscriber;
use App\Models\Subscription;
use App\Models\Ticket;
use App\Models\Zone;
use App\Services\Payments\FakePaymentGateway;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->zone = Zone::factory()->create();
    $fare = Fare::factory()->for($this->zone)->create(['amount' => 1500]);
    $this->customer = userWithRole(RoleName::Client);
    $this->payment = Payment::factory()->for($this->customer)->for($this->zone)->create(['fare_id' => $fare->id, 'amount' => 1500]);
});

/**
 * POST a notification signed like the fake aggregator (HMAC of the raw body).
 *
 * @param  array<string, mixed>  $body
 */
function sendWebhook(array $body, ?string $secret = 'testing-webhook-secret', string $gateway = 'fake'): TestResponse
{
    $raw = json_encode($body, JSON_THROW_ON_ERROR);
    $server = ['CONTENT_TYPE' => 'application/json'];

    if ($secret !== null) {
        $server['HTTP_X_SIGNATURE'] = hash_hmac('sha256', $raw, $secret);
    }

    return test()->call('POST', route('webhooks.payments', $gateway), [], [], [], $server, $raw);
}

it('issues the pass on a signed notification, after checking the status with the aggregator', function () {
    app(FakePaymentGateway::class)->record($this->payment, true);

    sendWebhook(['reference' => $this->payment->reference])->assertOk()->assertJson(['status' => 'received']);

    // QUEUE_CONNECTION=sync in tests: the job already ran.
    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Succeeded)
        ->and(Ticket::query()->where('payment_id', $this->payment->id)->count())->toBe(1);
});

it('issues a single pass when the notification is replayed', function () {
    app(FakePaymentGateway::class)->record($this->payment, true);

    foreach (range(1, 3) as $replay) {
        sendWebhook(['reference' => $this->payment->reference])->assertOk();
    }
    app(ConfirmPayment::class)->handle($this->payment->fresh()); // customer coming back too

    expect(Ticket::query()->count())->toBe(1);
});

it('cannot issue two passes for one payment, even bypassing the code (database guard)', function () {
    Ticket::factory()->create(['payment_id' => $this->payment->id]);

    expect(fn () => Ticket::factory()->create(['payment_id' => $this->payment->id]))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('trusts nothing in the notification: a signed "paid" message without real payment issues nothing', function () {
    // The aggregator API says the customer has not paid.
    sendWebhook(['reference' => $this->payment->reference, 'status' => 'ACCEPTED', 'amount' => 1500])->assertOk();

    expect($this->payment->fresh()->isPending())->toBeTrue()
        ->and(Ticket::query()->count())->toBe(0);
});

it('rejects and logs a notification with a bad signature', function (?string $secret) {
    app(FakePaymentGateway::class)->record($this->payment, true);

    sendWebhook(['reference' => $this->payment->reference], $secret)->assertUnauthorized();

    expect(Ticket::query()->count())->toBe(0)
        ->and($this->payment->fresh()->isPending())->toBeTrue()
        ->and(Activity::query()->where('log_name', 'webhooks')->where('event', 'webhook.rejected')->count())->toBe(1);
})->with([
    'no signature' => [null],
    'wrong secret' => ['not-the-secret'],
]);

it('refuses to issue when the amount reported by the aggregator differs', function () {
    Log::spy();
    app(FakePaymentGateway::class)->record($this->payment, true, amount: 15);

    sendWebhook(['reference' => $this->payment->reference])->assertOk();

    expect($this->payment->fresh())->status->toBe(PaymentStatus::Failed)->failure_reason->toBe('amount_mismatch')
        ->and(Ticket::query()->count())->toBe(0);
    Log::shouldHaveReceived('critical')->once();
});

it('refuses to issue when the currency differs', function () {
    app(FakePaymentGateway::class)->record($this->payment, true, currency: 'EUR');

    sendWebhook(['reference' => $this->payment->reference])->assertOk();

    expect($this->payment->fresh()->failure_reason)->toBe('amount_mismatch')
        ->and(Ticket::query()->count())->toBe(0);
});

it('acknowledges an unknown reference without doing anything', function () {
    sendWebhook(['reference' => 'TTUNKNOWN'])->assertOk();

    expect(Ticket::query()->count())->toBe(0);
});

it('ignores a notification sent to another gateway than the payment one', function () {
    app(FakePaymentGateway::class)->record($this->payment, true);
    config(['services.cinetpay.secret_key' => 'cinetpay-secret', 'services.cinetpay.site_id' => '123']);

    // Signed for "fake" but posted on the CinetPay endpoint: CinetPay check fails.
    sendWebhook(['reference' => $this->payment->reference], gateway: 'cinetpay')->assertUnauthorized();

    expect(Ticket::query()->count())->toBe(0);
});

it('does nothing more once the payment is final', function () {
    $fake = app(FakePaymentGateway::class);
    $fake->record($this->payment, false);
    sendWebhook(['reference' => $this->payment->reference]);
    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Failed);

    // The aggregator later claims it was accepted: a failed payment stays failed.
    $fake->record($this->payment, true);
    sendWebhook(['reference' => $this->payment->reference]);

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Failed)->and(Ticket::query()->count())->toBe(0);
});

it('issues a subscription for a monthly payment', function () {
    $subscriber = Subscriber::factory()->create(['user_id' => $this->customer->id]);
    $payment = Payment::factory()->monthly(50000)->for($this->customer)->for($this->zone)->create(['subscriber_id' => $subscriber->id]);
    app(FakePaymentGateway::class)->record($payment, true);

    sendWebhook(['reference' => $payment->reference])->assertOk();

    expect(Subscription::query()->where('payment_id', $payment->id)->sole()->price_paid)->toBe(50000)
        ->and($payment->fresh()->payable_type)->toBe(Subscription::class);
});

it('writes the payment lifecycle to the audit log', function () {
    app(FakePaymentGateway::class)->record($this->payment, true);
    sendWebhook(['reference' => $this->payment->reference]);

    expect(Activity::query()->where('log_name', 'payments')->pluck('event')->all())
        ->toContain('webhook.received')
        ->toContain('payment.succeeded');
});

it('rejects webhooks from an IP outside the allow-list', function () {
    config(['services.payment.webhook_allowed_ips' => '10.0.0.1']);
    app(FakePaymentGateway::class)->record($this->payment, true);

    sendWebhook(['reference' => $this->payment->reference])->assertForbidden();

    expect(Ticket::query()->count())->toBe(0);
});
