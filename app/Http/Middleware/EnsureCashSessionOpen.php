<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * No sale at the ticket office without an open cash register session.
 * Alias: "cash-session".
 */
class EnsureCashSessionOpen
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Fresh query (not the cached relation): the register may just have been closed.
        if ($user === null || ! $user->openCashSession()->exists()) {
            $message = 'Ouvrez votre caisse avant de vendre.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], Response::HTTP_CONFLICT);
            }

            return redirect()->route('guichet.cash.show')->with('warning', $message);
        }

        return $next($request);
    }
}
