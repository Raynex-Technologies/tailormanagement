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

    /*
    | SSL verification for API requests. On Windows/local dev, PHP may not have
    | a CA bundle and you get "unable to get local issuer certificate".
    | Run: php artisan beem:download-cacert  (saves bundle to storage/ssl/cacert.pem)
    | - true (default): use storage/ssl/cacert.pem if present, else system CA bundle
    | - false: disable verification (use only for local dev, not production)
    | - path string: path to cacert.pem (overrides project bundle)
    */
    'verify' => (function () {
        $env = env('BEEM_VERIFY_SSL');
        $match = match (strtolower((string) ($env ?? 'true'))) {
            'false', '0', 'no', 'off' => false,
            'true', '1', 'yes', 'on' => null,
            default => $env,
        };
        if ($match === false) {
            return false;
        }
        if (is_string($match) && $match !== '') {
            return $match;
        }
        $bundle = storage_path('ssl/cacert.pem');

        return file_exists($bundle) ? $bundle : true;
    })(),
];
