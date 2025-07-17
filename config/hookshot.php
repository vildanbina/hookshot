<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | HookShot Request Tracking
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration options for HookShot request tracking.
    | You can enable/disable tracking, choose storage drivers, and configure
    | filtering options to control what gets tracked.
    |
    */

    // Enable or disable request tracking globally
    'enabled' => (bool) env('HOOKSHOT_ENABLED', true),

    // Default storage driver: 'database', 'cache', or 'file'
    'default' => env('HOOKSHOT_DRIVER', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Storage Drivers
    |--------------------------------------------------------------------------
    |
    | Configure how and where request data is stored. Each driver has its own
    | settings for connection, retention, and other storage-specific options.
    |
    */
    'drivers' => [
        'database' => [
            'connection' => env('HOOKSHOT_DB_CONNECTION'),
            'table' => env('HOOKSHOT_TABLE', 'hookshot_logs'),
            'retention_days' => 30,
        ],

        'cache' => [
            'store' => env('HOOKSHOT_CACHE_STORE'),
            'prefix' => 'hookshot',
            'retention_days' => 7,
            'max_index_size' => 10000,
        ],

        'file' => [
            'path' => storage_path('app/hookshot'),
            'format' => 'json', // json or raw
            'retention_days' => 30,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Exclusion Filters
    |--------------------------------------------------------------------------
    |
    | Define which requests should be excluded from tracking to reduce noise
    | and focus on meaningful application requests.
    |
    */

    // Paths to exclude from tracking (health checks, admin panels, etc.)
    'excluded_paths' => [
        'health-check',
        'up',
        '_debugbar/*',
        'telescope/*',
        'horizon/*',
        'pulse/*',
    ],

    // User agents to exclude (monitoring tools, bots, crawlers)
    'excluded_user_agents' => [
        'pingdom',
        'uptimerobot',
        'googlebot',
        'bingbot',
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Controls
    |--------------------------------------------------------------------------
    |
    | Configure performance-related settings to manage resource usage and
    | ensure tracking doesn't impact application performance.
    |
    */

    // Percentage of requests to track (1.0 = 100%, 0.5 = 50%, etc.)
    'sampling_rate' => env('HOOKSHOT_SAMPLING_RATE', 1.0),

    // Maximum size of request payload to store (in bytes)
    'max_payload_size' => 65536, // 64KB

    // Maximum size of response body to store (in bytes)
    'max_response_size' => 10240, // 10KB

    // Queue request tracking for better performance
    'use_queue' => env('HOOKSHOT_USE_QUEUE', false),

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    |
    | Configure security-related settings for request tracking to ensure
    | sensitive data is properly filtered and protected.
    |
    */

    // Headers to filter/redact in request and response tracking
    'sensitive_headers' => [
        'authorization',
        'cookie',
        'set-cookie',
        'x-api-key',
        'x-auth-token',
        'x-csrf-token',
        'x-xsrf-token',
    ],

    /*
    |--------------------------------------------------------------------------
    | Response Capture Settings
    |--------------------------------------------------------------------------
    |
    | Configure which responses should be captured and stored for analysis.
    | These settings help control what response data is worth storing.
    |
    */

    // Content types to exclude from response body capture (prefix matching)
    'excluded_content_types' => [
        'image/',
        'video/',
        'audio/',
        'application/pdf',
        'application/zip',
        'application/octet-stream',
    ],

    // HTTP status codes that are considered important to capture
    'important_status_codes' => [
        200,  // OK
        201,  // Created
        400,  // Bad Request
        401,  // Unauthorized
        403,  // Forbidden
        404,  // Not Found
        422,  // Unprocessable Entity
        500,  // Internal Server Error
    ],
];
