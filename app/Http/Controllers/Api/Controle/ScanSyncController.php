<?php

namespace App\Http\Controllers\Api\Controle;

use App\Actions\Scans\SyncOfflineScans;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Controle\SyncScansRequest;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/controle/scans/sync: scans made without network, sent by the
 * phone once back online. Answers one line per scan (created | duplicate);
 * the phone then removes them from its queue.
 */
class ScanSyncController extends Controller
{
    public function __invoke(SyncScansRequest $request, SyncOfflineScans $sync): JsonResponse
    {
        $results = $sync->handle($request->user(), $request->scans());

        return response()->json([
            'results' => $results,
            'created' => count(array_filter($results, fn (array $r): bool => $r['status'] === 'created')),
            'duplicates' => count(array_filter($results, fn (array $r): bool => $r['status'] === 'duplicate')),
        ])->header('Cache-Control', 'no-store, private');
    }
}
