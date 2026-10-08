<?php

use App\Http\Controllers\Guichet\CashSessionController;
use App\Http\Controllers\Guichet\CustomerController;
use App\Http\Controllers\Guichet\SaleController;
use App\Http\Controllers\Guichet\SubscriberController;
use App\Http\Controllers\Guichet\SubscriptionSaleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Agency desk (/guichet) — guichetier
|--------------------------------------------------------------------------
| Group: auth + space.guichet. Selling requires an open cash register
| ("cash-session" middleware). Opening/closing act on the agent's own
| register only (no id in the URL).
*/

Route::middleware(['auth', 'permission:space.guichet'])
    ->prefix('guichet')
    ->name('guichet.')
    ->group(function () {
        Route::redirect('/', '/guichet/abonnement')->name('home');

        // Sales: subscriptions (main activity) and daily tickets
        Route::middleware(['permission:tickets.sell', 'cash-session'])->group(function () {
            Route::get('/abonnement', [SubscriptionSaleController::class, 'create'])->name('subscriptions.create');
            Route::post('/abonnement', [SubscriptionSaleController::class, 'store'])->name('subscriptions.store');

            Route::get('/vente', [SaleController::class, 'create'])->name('sales.create');
            Route::post('/vente', [SaleController::class, 'store'])->name('sales.store');

            Route::post('/ventes/{payment:reference}/annuler', [SaleController::class, 'cancel'])
                ->middleware('throttle:10,1')
                ->name('sales.cancel');
        });
        // Reprint also works after the register is closed.
        Route::get('/ventes/{payment:reference}/ticket', [SaleController::class, 'receipt'])
            ->middleware('permission:tickets.sell')
            ->name('sales.receipt');

        // Subscribers (abonnés)
        Route::get('/abonnes', [SubscriberController::class, 'index'])->middleware('permission:customers.view')->name('subscribers.index');
        Route::post('/abonnes', [SubscriberController::class, 'store'])->middleware('permission:customers.create')->name('subscribers.store');
        Route::get('/abonnes/{subscriber}/modifier', [SubscriberController::class, 'edit'])->middleware('permission:customers.create')->name('subscribers.edit');
        Route::put('/abonnes/{subscriber}', [SubscriberController::class, 'update'])->middleware('permission:customers.create')->name('subscribers.update');

        // Cash register
        Route::get('/caisse', [CashSessionController::class, 'show'])->middleware('permission:cash.open')->name('cash.show');
        Route::post('/caisse/ouvrir', [CashSessionController::class, 'open'])->middleware('permission:cash.open')->name('cash.open');
        Route::post('/caisse/cloturer', [CashSessionController::class, 'close'])->middleware('permission:cash.close')->name('cash.close');
        Route::get('/caisse/{cashSession}/rapport', [CashSessionController::class, 'report'])->middleware('permission:cash.open')->name('cash.report');

        // Customer accounts (online access): create, temporary password
        Route::get('/clients', [CustomerController::class, 'index'])->middleware('permission:customers.view')->name('customers.index');
        Route::post('/clients', [CustomerController::class, 'store'])->middleware(['permission:customers.create', 'throttle:20,1'])->name('customers.store');
        Route::post('/clients/{user}/mot-de-passe', [CustomerController::class, 'resetPassword'])
            ->middleware(['permission:customers.reset-password', 'throttle:10,1'])
            ->name('customers.password.reset');
    });
