<?php

use App\Http\Controllers\Api\Controle\ManifestController;
use App\Http\Controllers\Api\Controle\ScanSyncController;
use App\Http\Controllers\Api\MeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| JSON endpoints for our own pages (/api)
|--------------------------------------------------------------------------
| "web" group (bootstrap/app.php): same-origin session cookie + CSRF token,
| so a stolen URL alone is useless. Rate limited per user ("throttle:api").
*/

Route::middleware(['auth', 'throttle:api'])->group(function () {
    Route::get('/me', MeController::class)->name('me');

    // Checks without network on the bus (step 7, resources/js/controle/offline.js).
    Route::middleware('permission:scans.create')->prefix('controle')->name('controle.')->group(function () {
        Route::get('/manifeste', ManifestController::class)->name('manifest');
        Route::post('/scans/sync', ScanSyncController::class)->name('scans.sync');
    });
});
