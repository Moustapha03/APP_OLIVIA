<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Mobile Money payment gateway
    |--------------------------------------------------------------------------
    | driver: fake | cinetpay (others added behind the PaymentGateway contract)
    */

    'payment' => [
        'driver' => env('PAYMENT_DRIVER', 'fake'),
        // Comma separated IPs allowed to call the webhook (empty = no IP filter).
        'webhook_allowed_ips' => env('PAYMENT_WEBHOOK_ALLOWED_IPS', ''),
        'webhook_secret' => env('PAYMENT_WEBHOOK_SECRET'),
        'webhook_signature_header' => env('PAYMENT_WEBHOOK_SIGNATURE_HEADER', 'X-Signature'),
    ],

    'cinetpay' => [
        'base_url' => env('CINETPAY_BASE_URL', 'https://api-checkout.cinetpay.com/v2'),
        'site_id' => env('CINETPAY_SITE_ID'),
        'api_key' => env('CINETPAY_API_KEY'),
        'secret_key' => env('CINETPAY_SECRET_KEY'),
        // Payment page origin, allowed in the CSP form-action directive.
        'checkout_origin' => env('CINETPAY_CHECKOUT_ORIGIN', 'https://checkout.cinetpay.com'),
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS
    |--------------------------------------------------------------------------
    | driver: log (development) | a real provider added behind SmsSender
    */

    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
        'sender_name' => env('SMS_SENDER_NAME', 'TRANSPORT'),
        'api_url' => env('SMS_API_URL'),
        'api_key' => env('SMS_API_KEY'),
    ],

];
