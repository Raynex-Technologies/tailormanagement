<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Beem SMS API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Beem Africa SMS API integration.
    |
    */

    // Enable/disable SMS sending (useful for development/testing)
    'enabled' => env('BEEM_ENABLED', false),

    // API credentials
    'api_key' => env('BEEM_API_KEY', ''),
    'secret_key' => env('BEEM_SECRET_KEY', ''),

    // Sender ID (registered with Beem)
    'sender_id' => env('BEEM_SENDER_ID', 'INFO'),

    // API base URL
    'base_url' => env('BEEM_BASE_URL', 'https://apisms.beem.africa/v1'),

    // HTTP client settings
    'timeout' => env('BEEM_TIMEOUT', 30),
    'retry_times' => env('BEEM_RETRY_TIMES', 2),
    'retry_sleep' => env('BEEM_RETRY_SLEEP', 500), // milliseconds
];
