<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\HomeRoute;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

/**
 * Signed link from the confirmation e-mail. EmailVerificationRequest checks
 * that the link belongs to the logged-in user and to their current address.
 */
class VerifyEmailController extends Controller
{
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();

            activity('auth')->causedBy($user)->performedOn($user)
                ->event('email.verified')
                ->log('Adresse e-mail confirmée');
        }

        return redirect(HomeRoute::url($user))->with('success', 'Votre adresse e-mail est confirmée. Elle pourra servir si vous oubliez votre mot de passe.');
    }
}
