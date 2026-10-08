<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * A disabled account is logged out on its very next request, even if its
 * session is still alive (applied to the whole "web" group).
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->isActive()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $message = 'Votre compte a été désactivé. Contactez la compagnie de transport.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], Response::HTTP_UNAUTHORIZED)
                    ->header('Clear-Site-Data', '"storage"');
            }

            // Shared phones: offline copies and the controller's list of the day are erased.
            return redirect()->route('login')->with('error', $message)
                ->header('Clear-Site-Data', '"storage"');
        }

        return $next($request);
    }
}
