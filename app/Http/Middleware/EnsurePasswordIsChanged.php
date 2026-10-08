<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * After a password reset done by staff (temporary password), the user must
 * choose a new password before doing anything else.
 */
class EnsurePasswordIsChanged
{
    /** Routes still reachable while the password must be changed. */
    private const ALLOWED_ROUTES = [
        'account.password.edit',
        'account.password.update',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->must_change_password && ! $request->routeIs(self::ALLOWED_ROUTES)) {
            $message = 'Choisissez un nouveau mot de passe pour continuer.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], Response::HTTP_FORBIDDEN);
            }

            return redirect()->route('account.password.edit')->with('warning', $message);
        }

        return $next($request);
    }
}
