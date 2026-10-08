<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\UpdatePasswordRequest;
use App\Support\HomeRoute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Change one's own password. Also the forced screen after a temporary
 * password (EnsurePasswordIsChanged).
 */
class PasswordController extends Controller
{
    public function edit(Request $request): View
    {
        return view('account.password', [
            'forced' => (bool) $request->user()?->must_change_password,
        ]);
    }

    public function update(UpdatePasswordRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $user->forceFill([
            'password' => $request->validated('password'),
            'must_change_password' => false,
            'remember_token' => Str::random(60),
        ])->save();

        activity('auth')->causedBy($user)->performedOn($user)
            ->event('password.changed')
            ->withProperties(['ip' => $request->ip()])
            ->log('Mot de passe modifié par l’utilisateur');

        $request->session()->regenerate();

        return redirect(HomeRoute::url($user))->with('success', 'Votre mot de passe est modifié.');
    }
}
