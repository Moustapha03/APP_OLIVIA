<?php

use App\Http\Middleware\EnsureCashSessionOpen;
use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\LogSensitiveAction;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\VerifyWebhookSignature;
use App\Support\HomeRoute;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // One file per space. Each group repeats auth + its space permission.
            Route::middleware('web')->group(base_path('routes/admin.php'));
            Route::middleware('web')->group(base_path('routes/guichet.php'));
            Route::middleware('web')->group(base_path('routes/controle.php'));

            // JSON endpoints of our own pages (controller PWA sync…):
            // same-origin session + CSRF, so they stay in the "web" group.
            Route::middleware('web')->prefix('api')->name('api.')->group(base_path('routes/api.php'));

            // Server-to-server notifications: no session, no CSRF, signed instead.
            Route::middleware('api')->prefix('webhooks')->name('webhooks.')->group(base_path('routes/webhooks.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);

        // Host header: only the host of APP_URL is accepted (password reset and
        // e-mail verification links are built from it). Laravel skips this
        // check in "local" and in tests. Symfony reads each entry as a regular
        // expression, hence the anchors (no "example.com.evil.net").
        $middleware->trustHosts(
            at: function (): array {
                $host = parse_url((string) config('app.url'), PHP_URL_HOST);

                return is_string($host) && $host !== '' ? ['^'.preg_quote($host).'$'] : [];
            },
            subdomains: false,
        );

        // Reverse proxies whose X-Forwarded-* headers are believed: the list
        // comes from config/trustedproxy.php (TRUSTED_PROXIES, empty = none,
        // never "*" by default). Read there because .env is not loaded yet here.
        $middleware->trustProxies(
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );

        $middleware->web(append: [
            // Other sessions of a person end as soon as their password changes
            // (own change, e-mail reset, temporary password given by a manager).
            AuthenticateSession::class,
            EnsureUserIsActive::class,
            EnsurePasswordIsChanged::class,
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'cash-session' => EnsureCashSessionOpen::class,
            'webhook.signature' => VerifyWebhookSignature::class,
            'audit' => LogSensitiveAction::class,
        ]);

        $middleware->redirectUsersTo(fn (Request $request) => HomeRoute::url($request->user()));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Never put typed passwords back into the session after a validation error.
        $exceptions->dontFlash([
            'current_password',
            'password',
            'password_confirmation',
            'supervisor_password',
            'approver_password',
        ]);
    })->create();
