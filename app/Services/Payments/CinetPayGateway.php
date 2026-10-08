<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Enums\GatewayStatus;
use App\Models\Payment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * CinetPay checkout API v2 (https://docs.cinetpay.com).
 *
 * - POST {base}/payment        → data.payment_url
 * - POST {base}/payment/check  → data.status ACCEPTED | REFUSED | WAITING…
 * - notification: form POST with cpm_* fields, header "x-token" =
 *   HMAC-SHA256 of the concatenated fields with the secret key.
 *
 * To check against the CinetPay sandbox before production (DECISIONS.md).
 */
class CinetPayGateway implements PaymentGateway
{
    /** Concatenation order of the x-token HMAC (CinetPay documentation). */
    private const TOKEN_FIELDS = [
        'cpm_site_id', 'cpm_trans_id', 'cpm_trans_date', 'cpm_amount', 'cpm_currency', 'signature',
        'payment_method', 'cel_phone_num', 'cpm_phone_prefixe', 'cpm_language', 'cpm_version',
        'cpm_payment_config', 'cpm_page_action', 'cpm_custom', 'cpm_designation', 'cpm_error_message',
    ];

    public function name(): string
    {
        return 'cinetpay';
    }

    public function start(Payment $payment, string $returnUrl, string $notifyUrl): string
    {
        $payment->loadMissing(['user', 'stop', 'zone']);

        try {
            $response = Http::asJson()->acceptJson()->timeout(15)->post($this->url('/payment'), [
                'apikey' => $this->config('api_key'),
                'site_id' => $this->config('site_id'),
                'transaction_id' => $payment->reference,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'description' => mb_substr($payment->purpose->label().' '.($payment->stop->name ?? $payment->zone->name), 0, 100),
                'notify_url' => $notifyUrl,
                'return_url' => $returnUrl,
                'channels' => 'MOBILE_MONEY',
                'lang' => 'fr',
                'metadata' => (string) $payment->id,
                'customer_name' => $payment->user?->last_name,
                'customer_surname' => $payment->user?->first_name,
                'customer_phone_number' => $payment->user?->phone,
            ]);
        } catch (ConnectionException $e) {
            throw new PaymentGatewayException('CinetPay unreachable: '.$e->getMessage(), previous: $e);
        }

        $url = $response->json('data.payment_url');

        if (! $response->successful() || ! is_string($url) || ! str_starts_with($url, 'https://')) {
            Log::error('CinetPay payment init failed', ['reference' => $payment->reference, 'status' => $response->status(), 'body' => $response->json()]);

            throw new PaymentGatewayException('CinetPay refused the transaction: '.($response->json('message') ?? $response->status()));
        }

        return $url;
    }

    public function check(Payment $payment): GatewayResult
    {
        try {
            $response = Http::asJson()->acceptJson()->timeout(15)->post($this->url('/payment/check'), [
                'apikey' => $this->config('api_key'),
                'site_id' => $this->config('site_id'),
                'transaction_id' => $payment->reference,
            ]);
        } catch (ConnectionException $e) {
            return GatewayResult::waiting('CinetPay injoignable : '.$e->getMessage());
        }

        /** @var array<string, mixed> $body */
        $body = (array) $response->json();
        $data = (array) ($body['data'] ?? []);

        if (! $response->successful() && $data === []) {
            return GatewayResult::waiting('Réponse CinetPay inattendue ('.$response->status().').', $body);
        }

        $status = match (strtoupper((string) ($data['status'] ?? ''))) {
            'ACCEPTED' => GatewayStatus::Accepted,
            'REFUSED', 'CANCELED', 'CANCELLED' => GatewayStatus::Refused,
            default => GatewayStatus::Waiting,
        };

        return new GatewayResult(
            status: $status,
            amount: isset($data['amount']) ? (int) $data['amount'] : null,
            currency: isset($data['currency']) ? (string) $data['currency'] : null,
            transactionId: isset($data['operator_id']) ? (string) $data['operator_id'] : null,
            paymentMethod: isset($data['payment_method']) ? (string) $data['payment_method'] : null,
            message: isset($body['message']) ? (string) $body['message'] : null,
            raw: $body,
        );
    }

    public function verifyNotification(Request $request): bool
    {
        $secret = (string) $this->config('secret_key');
        $token = (string) $request->header('x-token');

        if ($secret === '' || $token === '') {
            return false;
        }

        if ((string) $request->input('cpm_site_id') !== (string) $this->config('site_id')) {
            return false;
        }

        $data = implode('', array_map(fn (string $field) => (string) $request->input($field, ''), self::TOKEN_FIELDS));

        return hash_equals(hash_hmac('sha256', $data, $secret), strtolower($token));
    }

    public function referenceFromNotification(Request $request): ?string
    {
        $reference = $request->input('cpm_trans_id');

        return is_string($reference) && $reference !== '' ? $reference : null;
    }

    public function checkoutOrigin(): ?string
    {
        return (string) config('services.cinetpay.checkout_origin', 'https://checkout.cinetpay.com');
    }

    private function url(string $path): string
    {
        return rtrim((string) $this->config('base_url'), '/').$path;
    }

    private function config(string $key): mixed
    {
        return config("services.cinetpay.{$key}");
    }
}
