<?php

declare(strict_types=1);

return [
    'enabled' => env('REQUEST_TRACKER_ENABLED', true),
    'default' => env('REQUEST_TRACKER_DRIVER', 'database'),

    // Auto-track all requests (global middleware) or only when explicitly applied
    'auto_track' => env('REQUEST_TRACKER_AUTO_TRACK', true),

    'drivers' => [
        'database' => [
            'connection' => env('REQUEST_TRACKER_DB_CONNECTION'),
            'table' => 'request_tracker_logs',
            'retention_days' => 30,
        ],

        'cache' => [
            'store' => env('REQUEST_TRACKER_CACHE_STORE'),
            'prefix' => 'request_tracker',
            'retention_days' => 7,
        ],

        'file' => [
            'path' => storage_path('app/request-tracker'),
            'format' => 'json', // json or raw
            'retention_days' => 30,
        ],
    ],

    // Paths to exclude from tracking
    'excluded_paths' => [
        'health-check',
        'up',
        '_debugbar/*',
        'telescope/*',
        'horizon/*',
        'pulse/*',
    ],

    // User agents to exclude (monitoring tools, bots)
    'excluded_user_agents' => [
        'pingdom',
        'uptimerobot',
        'googlebot',
        'bingbot',
    ],

    // Performance controls
    'sampling_rate' => env('REQUEST_TRACKER_SAMPLING_RATE', 1.0),
    'max_payload_size' => 65536, // 64KB
    'max_response_size' => 10240, // 10KB
    'use_queue' => env('REQUEST_TRACKER_USE_QUEUE', false),
];
