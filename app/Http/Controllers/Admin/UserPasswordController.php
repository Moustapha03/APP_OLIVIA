<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Users\ResetUserPassword;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Temporary password for a person who forgot theirs. Shown once on the
 * next screen, never stored in clear nor written to the logs.
 */
class UserPasswordController extends Controller
{
    public function store(Request $request, User $user, ResetUserPassword $reset): RedirectResponse
    {
        Gate::authorize('resetPassword', $user);

        $temporaryPassword = $reset->handle($request->user(), $user);

        return redirect()->route('admin.users.edit', $user)
            ->with('success', 'Mot de passe provisoire créé. Le compte est débloqué.')
            ->with('temporary_password', $temporaryPassword);
    }
}
