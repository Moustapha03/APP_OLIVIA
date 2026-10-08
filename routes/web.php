<?php

use App\Http\Controllers\Account\PasswordController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Design\DesignController;
use App\Http\Controllers\Espace\HomeController as EspaceHomeController;
use App\Http\Controllers\Espace\PassController;
use App\Http\Controllers\Espace\PaymentController;
use App\Http\Controllers\Espace\ProfileController;
use App\Http\Controllers\Espace\PurchaseController;
use App\Http\Controllers\Espace\SubscriberController as EspaceSubscriberController;
use App\Http\Controllers\FakePaymentController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ReceiptShareController;
use App\Http\Controllers\SubscriberPhotoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public + customer space (/espace)
|--------------------------------------------------------------------------
| Other spaces: routes/admin.php, guichet.php, controle.php (registered in
| bootstrap/app.php). Every group checks auth + a "space.*" permission.
*/

Route::get('/', HomeController::class)->name('home');

// Fallback page of the service worker (public/sw.js), cached at install.
Route::view('/hors-ligne', 'offline')->name('offline');

Route::middleware('auth')->group(function () {
    // Sends each user to their own space.
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Own password, for every user (also the forced change screen).
    Route::get('/compte/mot-de-passe', [PasswordController::class, 'edit'])->name('account.password.edit');
    Route::put('/compte/mot-de-passe', [PasswordController::class, 'update'])
        ->middleware('audit:Changement de mot de passe')
        ->name('account.password.update');

    // Subscriber photo, private (SubscriberPolicy::viewPhoto, or 5-min signed link from a scan).
    Route::get('/photos/abonnes/{subscriber}', SubscriberPhotoController::class)->name('photos.subscriber');

    // Receipt PDF and WhatsApp sending (staff: own sales or payments.view; customer: own payments)
    Route::get('/recus/{payment:reference}/pdf', [ReceiptShareController::class, 'download'])->middleware('throttle:30,1')->name('receipts.pdf');
    Route::get('/recus/{payment:reference}/whatsapp', [ReceiptShareController::class, 'whatsapp'])->middleware('throttle:30,1')->name('receipts.whatsapp');
});

Route::middleware(['auth', 'permission:space.espace'])
    ->prefix('espace')
    ->name('espace.')
    ->group(function () {
        Route::get('/', EspaceHomeController::class)->name('home');

        // Purchase: Arrêt → Formule → Paiement
        Route::middleware('permission:passes.purchase')->group(function () {
            Route::get('/acheter', [PurchaseController::class, 'create'])->name('purchase.create');
            Route::get('/acheter/{stop}', [PurchaseController::class, 'formula'])->name('purchase.formula');
            Route::post('/acheter', [PurchaseController::class, 'store'])->middleware('throttle:purchase')->name('purchase.store');
        });

        // My subscribers (myself, a relative…)
        Route::get('/abonnes', [EspaceSubscriberController::class, 'index'])->name('subscribers.index');
        Route::middleware('permission:passes.purchase')->group(function () {
            Route::post('/abonnes', [EspaceSubscriberController::class, 'store'])->middleware('throttle:10,1')->name('subscribers.store');
            Route::put('/abonnes/{subscriber}', [EspaceSubscriberController::class, 'update'])->name('subscribers.update');
        });

        // Back from the payment page (status verified server to server).
        Route::get('/paiements/{payment:reference}', [PaymentController::class, 'show'])->middleware('throttle:30,1')->name('payments.show');

        // My passes (by uuid, policy checked: IDOR)
        Route::get('/titres', [PassController::class, 'index'])->name('passes.index');
        Route::get('/titres/ticket/{ticket:uuid}', [PassController::class, 'ticket'])->name('tickets.show');
        Route::get('/titres/ticket/{ticket:uuid}/pdf', [PassController::class, 'ticketPdf'])->name('tickets.pdf');
        Route::get('/titres/abonnement/{subscription:uuid}', [PassController::class, 'subscription'])->name('subscriptions.show');
        Route::get('/titres/abonnement/{subscription:uuid}/pdf', [PassController::class, 'subscriptionPdf'])->name('subscriptions.pdf');

        Route::get('/profil', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profil', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profil', [ProfileController::class, 'destroy'])
            ->middleware('audit:Suppression de compte client')
            ->name('profile.destroy');
    });

/*
| Mobile Money simulator (driver "fake"). Never registered in production.
*/
if (! app()->isProduction()) {
    Route::middleware(['auth', 'signed'])->group(function () {
        Route::get('/paiement-test/{payment:reference}', [FakePaymentController::class, 'show'])->name('payments.fake.show');
        Route::post('/paiement-test/{payment:reference}', [FakePaymentController::class, 'decide'])->name('payments.fake.decide');
    });
}

/*
| Living style guide — super-admin only ("design.view" ability, granted
| to super-admin through Gate::before).
*/
// Link sent to the client on WhatsApp: signed and expiring, no account needed.
Route::get('/recu/{payment:reference}', [ReceiptShareController::class, 'shared'])
    ->middleware(['signed', 'throttle:30,1'])
    ->name('receipts.shared');

Route::middleware(['auth', 'can:design.view'])
    ->prefix('design')
    ->name('design.')
    ->controller(DesignController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/layouts/{space}', 'layout')->whereIn('space', DesignController::SPACES)->name('layout');
        Route::get('/scan/{status}', 'scan')->whereIn('status', DesignController::SCAN_STATUSES)->name('scan');
        Route::post('/demo', 'demo')->name('demo');
    });

require __DIR__.'/auth.php';
