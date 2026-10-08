<?php

use App\Enums\GatewayStatus;
use App\Enums\RoleName;
use App\Models\Payment;
use App\Models\Zone;
use App\Services\Payments\CinetPayGateway;
use App\Services\Payments\PaymentGatewayException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.cinetpay.base_url' => 'https://api-checkout.cinetpay.com/v2',
        'services.cinetpay.site_id' => '105900',
        'services.cinetpay.api_key' => 'api-key-123',
        'services.cinetpay.secret_key' => 'secret-key-456',
    ]);

    $this->payment = Payment::factory()
        ->for(userWithRole(RoleName::Client, ['first_name' => 'Awa', 'last_name' => 'Koné']))
        ->for(Zone::factory()->create(['name' => 'Bonoua / Bassam']))
        ->create(['gateway' => 'cinetpay', 'amount' => 2000]);
    $this->gateway = new CinetPayGateway;
});

it('opens the transaction and returns the payment page', function () {
    Http::fake(['api-checkout.cinetpay.com/v2/payment' => Http::response([
        'code' => '201', 'message' => 'CREATED', 'data' => ['payment_url' => 'https://checkout.cinetpay.com/payment/abc'],
    ])]);

    $url = $this->gateway->start($this->payment, 'https://app.test/retour', 'https://app.test/webhooks/paiements/cinetpay');

    expect($url)->toBe('https://checkout.cinetpay.com/payment/abc');
    Http::assertSent(fn (HttpRequest $request) => $request['transaction_id'] === $this->payment->reference
        && $request['amount'] === 2000
        && $request['currency'] === 'XOF'
        && $request['site_id'] === '105900'
        && $request['apikey'] === 'api-key-123'
        && $request['notify_url'] === 'https://app.test/webhooks/paiements/cinetpay'
        && $request['return_url'] === 'https://app.test/retour'
        && $request['customer_surname'] === 'Awa');
});

it('fails cleanly when CinetPay refuses or is down', function (Closure $response) {
    Http::fake(['*' => $response]);

    $this->gateway->start($this->payment, 'https://a.test', 'https://b.test');
})->with([
    'error' => [fn () => Http::response(['code' => '608', 'message' => 'MINIMUM_REQUIRED_FIELDS'], 400)],
    'no url' => [fn () => Http::response(['code' => '201', 'data' => []])],
    'not https' => [fn () => Http::response(['code' => '201', 'data' => ['payment_url' => 'http://evil.test']])],
])->throws(PaymentGatewayException::class);

it('maps the status API answers', function (string $status, GatewayStatus $expected) {
    Http::fake(['api-checkout.cinetpay.com/v2/payment/check' => Http::response([
        'code' => '00', 'message' => 'SUCCES',
        'data' => ['status' => $status, 'amount' => '2000', 'currency' => 'XOF', 'payment_method' => 'OMCIV2', 'operator_id' => 'OP123'],
    ])]);

    $result = $this->gateway->check($this->payment);

    expect($result->status)->toBe($expected)
        ->and($result->amount)->toBe(2000)
        ->and($result->currency)->toBe('XOF')
        ->and($result->paymentMethod)->toBe('OMCIV2');
})->with([
    ['ACCEPTED', GatewayStatus::Accepted],
    ['REFUSED', GatewayStatus::Refused],
    ['WAITING_CUSTOMER_PAYMENT', GatewayStatus::Waiting],
]);

it('reports "waiting" when CinetPay cannot be reached', function () {
    Http::fake(fn () => throw new ConnectionException('timeout'));

    expect($this->gateway->check($this->payment)->status)->toBe(GatewayStatus::Waiting);
});

/**
 * @param  array<string, string>  $fields
 */
function cinetPayNotification(array $fields, ?string $token): Request
{
    $request = Request::create('/webhooks/paiements/cinetpay', 'POST', $fields);
    if ($token !== null) {
        $request->headers->set('x-token', $token);
    }

    return $request;
}

it('verifies the x-token of a notification', function () {
    $fields = [
        'cpm_site_id' => '105900', 'cpm_trans_id' => $this->payment->reference, 'cpm_trans_date' => '20261005080000',
        'cpm_amount' => '2000', 'cpm_currency' => 'XOF', 'signature' => 'sig', 'payment_method' => 'OMCIV2',
        'cel_phone_num' => '0700000006', 'cpm_phone_prefixe' => '225', 'cpm_language' => 'fr', 'cpm_version' => 'V4',
        'cpm_payment_config' => 'SINGLE', 'cpm_page_action' => 'PAYMENT', 'cpm_custom' => '', 'cpm_designation' => 'Ticket', 'cpm_error_message' => '',
    ];
    $token = hash_hmac('sha256', implode('', $fields), 'secret-key-456');

    expect($this->gateway->verifyNotification(cinetPayNotification($fields, $token)))->toBeTrue()
        ->and($this->gateway->referenceFromNotification(cinetPayNotification($fields, $token)))->toBe($this->payment->reference)
        ->and($this->gateway->verifyNotification(cinetPayNotification([...$fields, 'cpm_amount' => '20'], $token)))->toBeFalse()
        ->and($this->gateway->verifyNotification(cinetPayNotification([...$fields, 'cpm_site_id' => '999'], hash_hmac('sha256', implode('', [...$fields, 'cpm_site_id' => '999']), 'secret-key-456'))))->toBeFalse()
        ->and($this->gateway->verifyNotification(cinetPayNotification($fields, null)))->toBeFalse();
});

it('rejects every notification when the secret key is missing', function () {
    config(['services.cinetpay.secret_key' => '']);

    expect($this->gateway->verifyNotification(cinetPayNotification(['cpm_site_id' => '105900'], hash_hmac('sha256', '105900', ''))))->toBeFalse();
});

it('allows the CinetPay payment page in the CSP form-action', function () {
    config(['services.payment.driver' => 'cinetpay']);

    expect($this->get(route('home'))->headers->get('Content-Security-Policy'))
        ->toContain("form-action 'self' https://checkout.cinetpay.com");
});
