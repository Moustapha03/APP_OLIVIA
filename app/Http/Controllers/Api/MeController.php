<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Navigation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Who am I? Used by the controller PWA to check its session is still
 * alive before syncing (step 7). Minimal data, no personal details beyond
 * the first name.
 */
class MeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        return response()->json([
            'id' => $user->id,
            'first_name' => $user->first_name,
            'spaces' => array_values(array_filter(
                Navigation::SPACES,
                fn (string $space) => $user->can("space.{$space}"),
            )),
        ]);
    }
}
