<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Support\HomeRoute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $user = $request->authenticate();

        // New session id after login: prevents session fixation.
        $request->session()->regenerate();

        if (HomeRoute::name($user) === null) {
            $this->logout($request);

            return redirect()->route('login')->with('error', 'Votre compte n’a accès à aucun espace. Contactez un responsable.');
        }

        return redirect()->intended(HomeRoute::url($user));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->logout($request);

        // Shared phones (agency, bus): the browser erases the offline copies of
        // pages, the controller's list of the day and the service worker.
        return redirect()->route('home')
            ->with('success', 'Vous êtes déconnecté. À bientôt !')
            ->header('Clear-Site-Data', '"storage"');
    }

    private function logout(Request $request): void
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
