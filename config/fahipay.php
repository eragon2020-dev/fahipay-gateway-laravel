<?php

return [
    /*
    |--------------------------------------------------------------------------
    | FahiPay Merchant Credentials (Shop ID)
    |--------------------------------------------------------------------------
    */
    'shop_id' => env('FAHIPAY_SHOP_ID', env('FAHIPAY_MERCHANT_ID', '')),
    'secret_key' => env('FAHIPAY_SECRET_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Test Mode
    |--------------------------------------------------------------------------
    */
    'test_mode' => env('FAHIPAY_TEST_MODE', false),
    'test_base_url' => env('FAHIPAY_TEST_BASE_URL', 'https://test.fahipay.mv/api/merchants'),
    'base_url' => env('FAHIPAY_BASE_URL', 'https://fahipay.mv/api/merchants'),
    'web_url' => env('FAHIPAY_WEB_URL', 'https://fahipay.mv'),

    /*
    |--------------------------------------------------------------------------
    | URLs (Callback URLs)
    |--------------------------------------------------------------------------
    */
    'return_url' => env('FAHIPAY_RETURN_URL', '/fahipay/callback/success'),
    'cancel_url' => env('FAHIPAY_CANCEL_URL', '/fahipay/callback/cancel'),
    'error_url' => env('FAHIPAY_ERROR_URL', '/fahipay/callback/error'),

    /*
    |--------------------------------------------------------------------------
    | API Settings
    |--------------------------------------------------------------------------
    */
    'timeout' => env('FAHIPAY_TIMEOUT', 30),
    'retry_attempts' => env('FAHIPAY_RETRY_ATTEMPTS', 3),

    /*
    |--------------------------------------------------------------------------
    | Payment Settings
    |--------------------------------------------------------------------------
    */
    'payment' => [
        'prefix' => env('FAHIPAY_TRANSACTION_PREFIX', 'PAY'),
        'unique_id_length' => 12,
        'expire_hours' => 24,
    ],

    /*
    |--------------------------------------------------------------------------
    | Web Routes
    |--------------------------------------------------------------------------
    */
    'routes' => [
        'enabled' => true,
        'prefix' => 'fahipay',
        'middleware' => ['web'],
    ],

    /*
    |--------------------------------------------------------------------------
    | API Routes
    |--------------------------------------------------------------------------
    */
    'api' => [
        'enabled' => false,
        'prefix' => 'api/fahipay',
        'middleware' => ['api'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    */
    'database' => [
        'enabled' => true,
        'connection' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Events
    |--------------------------------------------------------------------------
    */
    'events' => [
        \Fahipay\Gateway\Events\PaymentInitiatedEvent::class => [],
        \Fahipay\Gateway\Events\PaymentCompletedEvent::class => [],
        \Fahipay\Gateway\Events\PaymentFailedEvent::class => [],
        \Fahipay\Gateway\Events\PaymentCancelledEvent::class => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'enabled' => env('FAHIPAY_LOGGING', true),
        'channel' => env('FAHIPAY_LOG_CHANNEL', 'stack'),
    ],

    /*
    |--------------------------------------------------------------------------
    | UI Settings
    |--------------------------------------------------------------------------
    */
    'ui' => [
        'theme' => 'bootstrap',
        'views_namespace' => 'fahipay::',
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    */
    'allowed_redirect_hosts' => [],
];