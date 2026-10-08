<?php

namespace App\Http\Controllers\Auth;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Customer self sign-up: always the "client" role, nothing else.
 */
class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $user = DB::transaction(function () use ($request) {
            $user = new User($request->safe()->only(['first_name', 'last_name', 'phone', 'email', 'password']));
            $user->forceFill([
                'status' => UserStatus::Active,
                'privacy_accepted_at' => now(),
            ])->save();

            $user->assignRole(RoleName::Client->value);

            return $user;
        });

        event(new Registered($user));
        $user->sendEmailVerificationNotification();

        Auth::login($user);
        $request->session()->regenerate();

        $message = "Bienvenue {$user->first_name} ! Votre compte est créé.";

        if ($user->email !== null) {
            $message .= ' Ouvrez le lien reçu par e-mail pour confirmer votre adresse.';
        }

        return redirect()->route('espace.home')->with('success', $message);
    }
}
