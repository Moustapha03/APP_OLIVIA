<?php

use App\Http\Controllers\Controle\ScanController;
use App\Http\Controllers\Controle\TripController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| On-board control (/controle) — contrôleur
|--------------------------------------------------------------------------
| Group: auth + space.controle. The camera is allowed on these URLs only
| (Permissions-Policy, SecurityHeaders). Scan submissions: "throttle:scan".
*/

Route::middleware(['auth', 'permission:space.controle'])
    ->prefix('controle')
    ->name('controle.')
    ->group(function () {
        Route::redirect('/', '/controle/scanner')->name('home');

        Route::get('/scanner', [ScanController::class, 'create'])
            ->middleware('permission:scans.create')
            ->name('scan');

        Route::post('/scanner', [ScanController::class, 'store'])
            ->middleware(['permission:scans.create', 'throttle:scan'])
            ->name('scan.store');

        Route::get('/historique', [ScanController::class, 'index'])
            ->middleware('permission:scans.create')
            ->name('scans.index');

        Route::get('/voyage', [TripController::class, 'current'])
            ->middleware('permission:trips.manage-own')
            ->name('trips.current');
    });
