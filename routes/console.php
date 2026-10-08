<?php

use App\Models\Payment;
use App\Models\Scan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled tasks — run by "php artisan schedule:run" every minute (cron)
|--------------------------------------------------------------------------
| Personal data retention (law 2013-450): nothing is kept longer than needed.
*/

// Activity log: one year (config/activitylog.php).
Schedule::command('activitylog:clean --force')->dailyAt('02:00');

// Controls on board: one year (refund rule 7 needs only the current month).
Schedule::call(fn () => Scan::query()->where('scanned_at', '<', now()->subYear())->delete())
    ->name('scans:prune')->dailyAt('02:10');

// Raw payment gateway answers (may hold phone numbers): emptied after 13 months;
// the payment itself (amount, receipt, status) stays for the accounts.
Schedule::call(fn () => Payment::query()->whereNotNull('payload')->where('created_at', '<', now()->subMonths(13))->update(['payload' => null]))
    ->name('payments:forget-payload')->dailyAt('02:20');
