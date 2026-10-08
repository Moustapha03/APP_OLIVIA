<?php

namespace App\Http\Controllers\Api\Controle;

use App\Actions\Scans\BuildDailyManifest;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/controle/manifeste: passes valid today, for checks without
 * network on the bus (resources/js/controle/offline.js). Never cached by
 * the browser or a proxy; each download is written to the audit log.
 */
class ManifestController extends Controller
{
    public function __invoke(Request $request, BuildDailyManifest $build): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $manifest = $build->handle($user);

        activity('audit')->causedBy($user)
            ->event('scans.manifest')
            ->withProperties(['valid_for' => $manifest['valid_for'], 'passes' => count($manifest['passes'])])
            ->log('Liste du jour téléchargée (contrôle hors ligne)');

        return response()->json($manifest)
            ->header('Cache-Control', 'no-store, private');
    }
}
