<?php

namespace App\Http\Middleware;

use App\Services\Payments\PaymentGatewayManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mobile Money notifications: IP allow-list, then the signature check of
 * the aggregator named in the route ({gateway}): HMAC of the body for the
 * fake driver, CinetPay "x-token" for CinetPay. Fails closed (no secret,
 * unknown gateway = rejected). Every rejection is audit logged.
 * Alias: "webhook.signature".
 *
 * Even a valid signature only triggers a server-to-server status check:
 * the notification content itself is never trusted (ConfirmPayment).
 */
class VerifyWebhookSignature
{
    public function __construct(private readonly PaymentGatewayManager $gateways) {}

    public function handle(Request $request, Closure $next): Response
    {
        $allowedIps = array_filter(array_map('trim', explode(',', (string) config('services.payment.webhook_allowed_ips'))));

        if ($allowedIps !== [] && ! in_array($request->ip(), $allowedIps, true)) {
            return $this->reject($request, 'IP non autorisée', Response::HTTP_FORBIDDEN);
        }

        try {
            $gateway = $this->gateways->driver((string) $request->route('gateway'));
        } catch (InvalidArgumentException) {
            return $this->reject($request, 'Agrégateur inconnu ou désactivé', Response::HTTP_NOT_FOUND);
        }

        if (! $gateway->verifyNotification($request)) {
            return $this->reject($request, 'Signature invalide', Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }

    private function reject(Request $request, string $reason, int $status): Response
    {
        $context = [
            'reason' => $reason,
            'gateway' => $request->route('gateway'),
            'ip' => $request->ip(),
            'path' => $request->path(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
        ];

        Log::warning('Webhook rejected', $context);

        activity('webhooks')
            ->event('webhook.rejected')
            ->withProperties($context)
            ->log("Webhook rejeté : {$reason}");

        return response()->json(['message' => 'Unauthorized.'], $status);
    }
}
