<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Re-send the e-mail confirmation link (optional e-mail address).
 */
class EmailVerificationNotificationController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user === null || $user->email === null) {
            return back()->with('warning', 'Ajoutez d’abord une adresse e-mail.');
        }

        if ($user->hasVerifiedEmail()) {
            return back()->with('info', 'Votre adresse e-mail est déjà confirmée.');
        }

        $user->sendEmailVerificationNotification();

        return back()->with('success', 'Lien de confirmation envoyé à '.$user->email.'.');
    }
}
