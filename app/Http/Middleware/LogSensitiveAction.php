<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Writes a successful sensitive action to the audit log.
 *
 *   Route::put('/fares/{fare}', …)->middleware('audit:Modification de tarif');
 *
 * Only state-changing requests (not GET/HEAD) that succeeded are logged;
 * a request bounced back by validation is not. Secrets are never stored.
 */
class LogSensitiveAction
{
    /** Never written to the log. */
    private const HIDDEN_INPUT = [
        '_token',
        '_method',
        'password',
        'password_confirmation',
        'current_password',
        'photo',
    ];

    public function handle(Request $request, Closure $next, string $description = 'Action sensible'): Response
    {
        $response = $next($request);

        if ($request->isMethodSafe() || ! $this->succeeded($request, $response)) {
            return $response;
        }

        $activity = activity('audit')
            ->event($request->route()?->getName() ?? $request->path())
            ->withProperties([
                'route' => $request->route()?->getName(),
                'method' => $request->method(),
                'ip' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
                'parameters' => $this->routeParameters($request),
                'input' => $this->sanitizedInput($request),
            ]);

        if ($request->user() !== null) {
            $activity->causedBy($request->user());
        }

        $activity->log($description);

        return $response;
    }

    private function succeeded(Request $request, Response $response): bool
    {
        if ($response->isSuccessful()) {
            return true;
        }

        // Redirect after success, but not the redirect back of a failed validation.
        // Only errors flashed by THIS request count: the session still holds the
        // errors flashed by the previous one.
        $flashedNow = $request->hasSession() ? (array) $request->session()->get('_flash.new', []) : [];

        return $response->isRedirection() && ! in_array('errors', $flashedNow, true);
    }

    /**
     * @return array<string, mixed>
     */
    private function routeParameters(Request $request): array
    {
        return collect($request->route()?->parameters() ?? [])
            ->map(fn ($value) => $value instanceof Model ? $value->getKey() : $value)
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function sanitizedInput(Request $request): array
    {
        return collect($request->except(self::HIDDEN_INPUT))
            ->map(fn ($value) => is_string($value) ? mb_substr($value, 0, 500) : $value)
            ->all();
    }
}
