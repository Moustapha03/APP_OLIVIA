<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

/*
| Phone number + password for everyone. No SMS code, no 2FA (DECISIONS.md).
| The optional e-mail address only serves to receive a reset link.
*/

Route::middleware('guest')->group(function () {
    Route::get('inscription', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('inscription', [RegisteredUserController::class, 'store'])->middleware('throttle:register');

    Route::get('connexion', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('connexion', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login');

    Route::get('mot-de-passe-oublie', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('mot-de-passe-oublie', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:password-reset')
        ->name('password.email');

    Route::get('reinitialiser-mot-de-passe/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('reinitialiser-mot-de-passe', [NewPasswordController::class, 'store'])
        ->middleware('throttle:password-reset')
        ->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::get('verifier-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/lien-de-confirmation', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirmer-mot-de-passe', [ConfirmablePasswordController::class, 'show'])->name('password.confirm');
    Route::post('confirmer-mot-de-passe', [ConfirmablePasswordController::class, 'store'])->middleware('throttle:login');

    Route::post('deconnexion', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
