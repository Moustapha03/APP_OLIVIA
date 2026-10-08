<?php

namespace App\Http\Controllers;

use App\Support\HomeRoute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * "/dashboard": sends each user to the home of their space.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null || HomeRoute::name($user) === null, 403);

        return redirect(HomeRoute::url($user));
    }
}
