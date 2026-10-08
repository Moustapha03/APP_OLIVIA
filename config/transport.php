<?php

/*
|--------------------------------------------------------------------------
| Transport business configuration
|--------------------------------------------------------------------------
|
| Default business rules. Values editable at runtime (fares, stops...)
| live in the database; these are the bootstrap defaults and secrets.
|
*/

return [

    'currency' => env('TRANSPORT_CURRENCY', 'XOF'),

    'tickets' => [
        // Number of rides allowed on a daily ticket (2 = round trip).
        'daily_rides' => (int) env('TRANSPORT_DAILY_RIDES', 2),
    ],

    'subscriptions' => [
        // Rolling validity of a monthly subscription, in days.
        'duration_days' => (int) env('TRANSPORT_SUBSCRIPTION_DAYS', 30),
        // SMS reminder sent N days before expiry.
        'reminder_days_before' => (int) env('TRANSPORT_SUBSCRIPTION_REMINDER_DAYS', 3),
        // Max photo upload size, in kilobytes.
        'photo_max_kb' => (int) env('TRANSPORT_PHOTO_MAX_KB', 2048),
    ],

    'counter' => [
        // Thermal printer paper width, in mm: 58 or 80.
        'receipt_width' => (int) env('TRANSPORT_RECEIPT_WIDTH', 80),
        // Upper bound of the opening float, against typing mistakes.
        'max_opening_float' => (int) env('TRANSPORT_MAX_OPENING_FLOAT', 500000),
    ],

    'scans' => [
        // A pass already accepted within this window gives a warning "déjà scanné"
        // (anti-sharing) and no ride is counted again.
        'anti_replay_minutes' => (int) env('TRANSPORT_ANTI_REPLAY_MINUTES', 30),
        // Time (HH:MM, Africa/Abidjan) from which a scan counts as the evening trip:
        // "Matin simple" is refused after it, "Soir simple" before it.
        'evening_starts_at' => env('TRANSPORT_EVENING_STARTS_AT', '12:00'),
    ],

    'qr' => [
        // Current HMAC-SHA256 signing key and key id.
        'key_id' => env('QR_SIGNING_KEY_ID', 'k1'),
        'key' => env('QR_SIGNING_KEY'),
        // Previous keys still accepted during rotation, format "id:key,id:key".
        'previous_keys' => env('QR_SIGNING_PREVIOUS_KEYS', ''),
    ],

    'security' => [
        // Staff never get a "remember me" cookie: their session ends after
        // SESSION_LIFETIME minutes of inactivity (120 by default).
        'max_login_attempts' => (int) env('MAX_LOGIN_ATTEMPTS', 5),
        'lockout_minutes' => (int) env('LOCKOUT_MINUTES', 15),
    ],

    'reports' => [
        // Comma separated recipients of the daily report e-mail.
        'daily_recipients' => env('DAILY_REPORT_RECIPIENTS', ''),
    ],

];
