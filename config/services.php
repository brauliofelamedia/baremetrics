<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    'stripe' => [
        'publishable_key' => env('STRIPE_PUBLISHABLE_KEY'),
        'secret_key' => env('STRIPE_SECRET_KEY'),
    ],

    'baremetrics' => [
        'environment' => env('BAREMETRICS_ENVIRONMENT', 'sandbox'), // 'sandbox' or 'production'
        'sandbox_key' => env('BAREMETRICS_SANDBOX_KEY'),
        'live_key' => env('BAREMETRICS_LIVE_KEY'),
        'sandbox_url' => 'https://api-sandbox.baremetrics.com/v1',
        'production_url' => 'https://api.baremetrics.com/v1',
        'barecancel_js_url' => env('BAREMETRICS_BARECANCEL_JS_URL', 'https://baremetrics-barecancel.baremetrics.com/js/application.js'),
    ],

    'paypal' => [
        'client_id' => env('PAYPAL_CLIENT_ID'),
        'secret' => env('PAYPAL_SECRET'),
        'sandbox' => env('PAYPAL_SANDBOX', true),
        'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
    ],

    'gohighlevel' => [
        'authorization_url' => env('GHL_AUTORIZATION_URL'),
        'scopes' => env('GHL_SCOPES'),
        'client_id' => env('GHL_CLIENT_ID'),
        'code' => env('GHL_CODE'),
        'client_secret' => env('GHL_CLIENT_SECRET'),
        'access_token' => env('GHL_TOKEN'),
        'location_id' => env('GHL_LOCATION_ID'),
        'company' => env('GHL_COMPANY'),
        'notification_email' => env('GHL_NOTIFICATION_EMAIL'),
    ]

];
