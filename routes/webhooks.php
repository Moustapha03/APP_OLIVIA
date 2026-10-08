<?php

use App\Http\Controllers\Webhooks\PaymentWebhookController;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile Money notifications (/webhooks)
|--------------------------------------------------------------------------
| "api" group: no session, no CSRF. Every route MUST use:
|
|   ->middleware(['webhook.signature', 'throttle:webhooks'])
|
| Processing is idempotent and queued; the pass is issued only after a
| server-to-server status check (App\Actions\Payments\ConfirmPayment).
*/

Route::post('/paiements/{gateway}', PaymentWebhookController::class)
    ->whereIn('gateway', PaymentGatewayManager::DRIVERS)
    ->middleware(['webhook.signature', 'throttle:webhooks'])
    ->name('payments');
