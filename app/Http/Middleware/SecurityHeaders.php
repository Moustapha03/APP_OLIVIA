<?php

namespace App\Http\Middleware;

use App\Services\Payments\PaymentGatewayManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers on every response (global middleware).
 *
 * CSP: scripts only from our origin + a per-request nonce (Vite tags get it
 * automatically). No 'unsafe-eval' (Alpine CSP build) and no inline styles.
 * The camera is only allowed on the controller app (/controle).
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        Vite::useCspNonce();

        $response = $next($request);

        // Laravel's debug error page relies on inline scripts and styles.
        $isDebugErrorPage = config('app.debug') && $response->getStatusCode() >= 500;

        if (! $isDebugErrorPage) {
            $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy());
        }

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');
        $response->headers->set('Permissions-Policy', $this->permissionsPolicy($request));
        $response->headers->remove('X-Powered-By');

        if (app()->isProduction() && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    private function contentSecurityPolicy(): string
    {
        $nonce = Vite::cspNonce();

        $script = ["'self'", "'nonce-{$nonce}'"];
        // Nonce: lets our own <style nonce> blocks through (print page size), nothing else.
        $style = ["'self'", "'nonce-{$nonce}'"];
        $connect = ["'self'"];

        // `npm run dev`: allow the Vite dev server (scripts, injected styles, HMR socket).
        if (Vite::isRunningHot()) {
            $hot = rtrim((string) file_get_contents(Vite::hotFile()));
            $socket = preg_replace('#^http#', 'ws', $hot);

            $script[] = $hot;
            $style[] = $hot;
            $style[] = "'unsafe-inline'";
            $connect[] = $hot;
            $connect[] = (string) $socket;
        }

        $directives = [
            'default-src' => ["'self'"],
            'script-src' => $script,
            'style-src' => $style,
            'img-src' => ["'self'", 'data:', 'blob:'],
            'font-src' => ["'self'", 'data:'],
            'connect-src' => $connect,
            'media-src' => ["'self'"],
            'worker-src' => ["'self'"],
            'manifest-src' => ["'self'"],
            'form-action' => $this->formActionSources(),
            'frame-ancestors' => ["'none'"],
            'base-uri' => ["'self'"],
            'object-src' => ["'none'"],
        ];

        $policy = collect($directives)
            ->map(fn (array $sources, string $directive) => $directive.' '.implode(' ', $sources))
            ->implode('; ');

        return app()->isProduction() ? $policy.'; upgrade-insecure-requests' : $policy;
    }

    /**
     * The purchase form redirects to the aggregator payment page: Chrome
     * applies form-action to that redirect, so its origin must be allowed.
     *
     * @return list<string>
     */
    private function formActionSources(): array
    {
        try {
            $origin = app(PaymentGatewayManager::class)->driver()->checkoutOrigin();
        } catch (InvalidArgumentException) {
            $origin = null;
        }

        return $origin !== null ? ["'self'", $origin] : ["'self'"];
    }

    private function permissionsPolicy(Request $request): string
    {
        $camera = $request->is('controle', 'controle/*') ? 'camera=(self)' : 'camera=()';

        return implode(', ', [
            $camera,
            'microphone=()',
            'geolocation=()',
            'payment=()',
            'usb=()',
            'interest-cohort=()',
        ]);
    }
}
