<?php

namespace App\Http\Controllers\Espace;

use App\Http\Controllers\Controller;
use App\Http\Requests\Espace\DeleteAccountRequest;
use App\Http\Requests\Espace\UpdateProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        Gate::authorize('space.espace');

        return view('espace.profile.edit', ['user' => $request->user()]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        Gate::authorize('space.espace');

        $user = $request->user();
        abort_if($user === null, 403);

        $user->fill($request->validated());
        $emailChanged = $user->isDirty('email');

        if ($emailChanged) {
            // A new address must be confirmed again before it can receive a reset link.
            $user->email_verified_at = null;
        }

        $user->save();

        if ($emailChanged && $user->email !== null) {
            $user->sendEmailVerificationNotification();

            return redirect()->route('espace.profile.edit')->with('success', 'Profil enregistré. Ouvrez le lien envoyé à '.$user->email.' pour confirmer l’adresse.');
        }

        return redirect()->route('espace.profile.edit')->with('success', 'Profil enregistré.');
    }

    /**
     * Right to erasure (law n° 2013-450).
     */
    public function destroy(DeleteAccountRequest $request): RedirectResponse
    {
        Gate::authorize('space.espace');

        $user = $request->user();
        abort_if($user === null, 403);

        activity('auth')->performedOn($user)->event('account.deleted')
            ->withProperties(['ip' => $request->ip(), 'user_id' => $user->id])
            ->log('Compte client supprimé à sa demande');

        // Account data erased; passes, payments and subscribers (who may be
        // other people, managed by the agency) are kept and detached from
        // the account (user_id set to null by the foreign keys).
        Auth::logout();
        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('success', 'Votre compte est supprimé.');
    }
}
