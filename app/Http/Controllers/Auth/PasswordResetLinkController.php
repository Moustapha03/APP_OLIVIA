<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

/**
 * Reset link by e-mail, only to a VERIFIED address: a typo in an
 * unverified address must not hand the account to someone else.
 * Same answer whatever happens, so nobody can probe which addresses exist.
 * No (verified) e-mail: the ticket office resets the password.
 */
class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(ForgotPasswordRequest $request): RedirectResponse
    {
        $email = $request->validated('email');

        $hasVerifiedEmail = User::query()
            ->where('email', $email)
            ->whereNotNull('email_verified_at')
            ->exists();

        if ($hasVerifiedEmail) {
            Password::sendResetLink(['email' => $email]);
        }

        return back()->with('success', 'Si cette adresse est confirmée sur un compte, un lien vient de lui être envoyé. Pensez à regarder dans les courriers indésirables.');
    }
}
