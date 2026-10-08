<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ExpenseCategoryController;
use App\Http\Controllers\Admin\ExpenseController;
use App\Http\Controllers\Admin\FareController;
use App\Http\Controllers\Admin\LineController;
use App\Http\Controllers\Admin\ReceiptController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\StopController;
use App\Http\Controllers\Admin\SubscriberController;
use App\Http\Controllers\Admin\SubscriberImportController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserPasswordController;
use App\Http\Controllers\Admin\VehicleController;
use App\Http\Controllers\Admin\ZoneController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Back office (/admin) — super-admin, gestionnaire, direction, comptable
|--------------------------------------------------------------------------
| Group: auth + space.admin. Each route adds its own permission; the
| controllers check the policies again (Gate::authorize / Form Requests).
| Reference data changes are logged by the models (LogsActivity),
| account changes by the user Actions.
*/

Route::middleware(['auth', 'permission:space.admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', DashboardController::class)
            ->middleware('permission:reports.view')
            ->name('dashboard');

        // Lines, fare zones and boarding points
        Route::middleware('permission:lines.view')->group(function () {
            Route::get('/lignes', [LineController::class, 'index'])->name('lines.index');
            Route::get('/lignes/nouvelle', [LineController::class, 'create'])->middleware('permission:lines.manage')->name('lines.create');
            Route::post('/lignes', [LineController::class, 'store'])->middleware('permission:lines.manage')->name('lines.store');
            Route::get('/lignes/{line}', [LineController::class, 'show'])->name('lines.show');
            Route::get('/lignes/{line}/modifier', [LineController::class, 'edit'])->middleware('permission:lines.manage')->name('lines.edit');
            Route::put('/lignes/{line}', [LineController::class, 'update'])->middleware('permission:lines.manage')->name('lines.update');
            Route::delete('/lignes/{line}', [LineController::class, 'destroy'])->middleware('permission:lines.manage')->name('lines.destroy');

            // Zones and boarding points: /admin/lignes/{line}/zones/{zone}/points/{stop}
            Route::scopeBindings()->middleware('permission:stops.manage')->group(function () {
                Route::post('/lignes/{line}/zones', [ZoneController::class, 'store'])->name('lines.zones.store');
                Route::get('/lignes/{line}/zones/{zone}/modifier', [ZoneController::class, 'edit'])->name('lines.zones.edit');
                Route::put('/lignes/{line}/zones/{zone}', [ZoneController::class, 'update'])->name('lines.zones.update');
                Route::post('/lignes/{line}/zones/{zone}/deplacer', [ZoneController::class, 'move'])->name('lines.zones.move');
                Route::delete('/lignes/{line}/zones/{zone}', [ZoneController::class, 'destroy'])->name('lines.zones.destroy');

                Route::post('/lignes/{line}/zones/{zone}/points', [StopController::class, 'store'])->name('lines.zones.stops.store');
                Route::get('/lignes/{line}/zones/{zone}/points/{stop}/modifier', [StopController::class, 'edit'])->name('lines.zones.stops.edit');
                Route::put('/lignes/{line}/zones/{zone}/points/{stop}', [StopController::class, 'update'])->name('lines.zones.stops.update');
                Route::delete('/lignes/{line}/zones/{zone}/points/{stop}', [StopController::class, 'destroy'])->name('lines.zones.stops.destroy');
            });
        });

        // Fares: set from a date, corrected, removed (used ones are kept for history)
        Route::middleware('permission:fares.view')->group(function () {
            Route::get('/tarifs', [FareController::class, 'index'])->name('fares.index');
            Route::get('/tarifs/nouveau', [FareController::class, 'create'])->middleware('permission:fares.manage')->name('fares.create');
            Route::post('/tarifs', [FareController::class, 'store'])->middleware('permission:fares.manage')->name('fares.store');
            Route::put('/tarifs/{fare}', [FareController::class, 'update'])->middleware('permission:fares.manage')->name('fares.update');
            Route::delete('/tarifs/{fare}', [FareController::class, 'destroy'])->middleware('permission:fares.manage')->name('fares.destroy');
        });

        // Vehicles
        Route::middleware('permission:vehicles.view')->group(function () {
            Route::get('/vehicules', [VehicleController::class, 'index'])->name('vehicles.index');
            Route::get('/vehicules/nouveau', [VehicleController::class, 'create'])->middleware('permission:vehicles.manage')->name('vehicles.create');
            Route::post('/vehicules', [VehicleController::class, 'store'])->middleware('permission:vehicles.manage')->name('vehicles.store');
            Route::get('/vehicules/{vehicle}/modifier', [VehicleController::class, 'edit'])->middleware('permission:vehicles.manage')->name('vehicles.edit');
            Route::put('/vehicules/{vehicle}', [VehicleController::class, 'update'])->middleware('permission:vehicles.manage')->name('vehicles.update');
            Route::delete('/vehicules/{vehicle}', [VehicleController::class, 'destroy'])->middleware('permission:vehicles.manage')->name('vehicles.destroy');
        });

        // Accounts
        Route::middleware('permission:users.view')->group(function () {
            Route::get('/utilisateurs', [UserController::class, 'index'])->name('users.index');
            Route::get('/utilisateurs/nouveau', [UserController::class, 'create'])->middleware('permission:users.manage')->name('users.create');
            Route::post('/utilisateurs', [UserController::class, 'store'])->middleware('permission:users.manage')->name('users.store');
            Route::get('/utilisateurs/{user}/modifier', [UserController::class, 'edit'])->middleware('permission:users.manage')->name('users.edit');
            Route::put('/utilisateurs/{user}', [UserController::class, 'update'])->middleware('permission:users.manage')->name('users.update');
            Route::post('/utilisateurs/{user}/mot-de-passe', [UserPasswordController::class, 'store'])->middleware(['permission:users.manage', 'throttle:10,1'])->name('users.password.reset');
        });

        // Clients (abonnés): file, payment history, delete = archive once they paid
        Route::middleware('permission:customers.view|subscriptions.view-all')->group(function () {
            Route::get('/clients', [SubscriberController::class, 'index'])->name('subscribers.index');
            // Excel list: upload → preview → confirm (before /clients/{subscriber})
            Route::middleware('permission:customers.create')->group(function () {
                Route::get('/clients/importer', [SubscriberImportController::class, 'create'])->name('subscribers.import');
                Route::post('/clients/importer', [SubscriberImportController::class, 'preview'])->middleware('throttle:20,1')->name('subscribers.import.preview');
                Route::post('/clients/importer/confirmer', [SubscriberImportController::class, 'store'])->name('subscribers.import.store');
                Route::get('/clients/importer/modele', [SubscriberImportController::class, 'template'])->name('subscribers.import.template');
            });
            Route::get('/clients/nouveau', [SubscriberController::class, 'create'])->middleware('permission:customers.create')->name('subscribers.create');
            Route::post('/clients', [SubscriberController::class, 'store'])->middleware('permission:customers.create')->name('subscribers.store');
            Route::get('/clients/{subscriber}', [SubscriberController::class, 'show'])->withTrashed()->name('subscribers.show');
            Route::get('/clients/{subscriber}/modifier', [SubscriberController::class, 'edit'])->middleware('permission:customers.create')->name('subscribers.edit');
            Route::put('/clients/{subscriber}', [SubscriberController::class, 'update'])->middleware('permission:customers.create')->name('subscribers.update');
            Route::delete('/clients/{subscriber}', [SubscriberController::class, 'destroy'])->middleware('permission:customers.delete')->name('subscribers.destroy');
        });

        // Payment history (every transaction) and paid receipts by month, CSV export in the agency format
        Route::middleware('permission:subscriptions.view-all')->group(function () {
            Route::get('/recus', [ReceiptController::class, 'index'])->name('receipts.index');
            Route::get('/recus/export', [ReceiptController::class, 'export'])->middleware(['permission:reports.export', 'throttle:10,1'])->name('receipts.export');
            Route::get('/recus/{payment:reference}', [ReceiptController::class, 'show'])->name('receipts.show');
            Route::get('/recus/{payment:reference}/imprimer', [ReceiptController::class, 'print'])->name('receipts.print');
        });

        // Expenses by type: list and totals, create, edit, delete (archive), receipts, export
        Route::middleware('permission:expenses.view')->group(function () {
            Route::get('/depenses', [ExpenseController::class, 'index'])->name('expenses.index');
            Route::get('/depenses/export', [ExpenseController::class, 'export'])->middleware(['permission:reports.export', 'throttle:10,1'])->name('expenses.export');
            Route::get('/depenses/{expense}/justificatif', [ExpenseController::class, 'attachment'])->name('expenses.attachment');

            Route::middleware('permission:expenses.manage')->group(function () {
                Route::get('/depenses/nouvelle', [ExpenseController::class, 'create'])->name('expenses.create');
                Route::post('/depenses', [ExpenseController::class, 'store'])->middleware('throttle:30,1')->name('expenses.store');
                Route::get('/depenses/{expense}/modifier', [ExpenseController::class, 'edit'])->name('expenses.edit');
                Route::put('/depenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
                Route::delete('/depenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');

                Route::post('/depenses/types', [ExpenseCategoryController::class, 'store'])->name('expenses.categories.store');
                Route::put('/depenses/types/{category}', [ExpenseCategoryController::class, 'update'])->name('expenses.categories.update');
                Route::delete('/depenses/types/{category}', [ExpenseCategoryController::class, 'destroy'])->name('expenses.categories.destroy');
            });
        });

        Route::get('/roles', [RoleController::class, 'index'])->middleware('permission:roles.manage')->name('roles.index');
    });
