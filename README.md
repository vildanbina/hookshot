# HookShot

[![Tests](https://github.com/vildanbina/hookshot/actions/workflows/tests.yml/badge.svg)](https://github.com/vildanbina/hookshot/actions/workflows/tests.yml)
[![Latest Stable Version](https://poser.pugx.org/vildanbina/hookshot/v/stable)](https://packagist.org/packages/vildanbina/hookshot)
[![Total Downloads](https://poser.pugx.org/vildanbina/hookshot/downloads)](https://packagist.org/packages/vildanbina/hookshot)
[![License](https://poser.pugx.org/vildanbina/hookshot/license)](https://packagist.org/packages/vildanbina/hookshot)
[![PHP Version Require](https://poser.pugx.org/vildanbina/hookshot/require/php)](https://packagist.org/packages/vildanbina/hookshot)

A Laravel package for capturing and tracking HTTP requests with configurable storage drivers and filtering options.

## Overview

HookShot provides middleware-based HTTP request tracking for Laravel applications. It captures request/response data and
stores it using database, cache, or file storage drivers with configurable filtering and performance controls.

**How it works:** The middleware captures request data during the request lifecycle and stores the complete
request/response information during Laravel's `terminate` phase, ensuring your application's response time is not
affected by the logging process.

### Why HookShot?
- **🔍 Debug Issues** - Reproduce bugs by seeing exactly what requests caused them
- **📊 Analytics** - Track API usage patterns, popular endpoints, and user behavior
- **🛡️ Security** - Monitor suspicious requests and track authentication attempts
- **📋 Compliance** - Meet audit requirements with comprehensive request logging
- **⚡ Performance** - Identify slow endpoints and optimize request handling
- **🔌 API Monitoring** - Track external API integrations and webhook deliveries

## Requirements

- PHP 8.2+
- Laravel 11.x, 12.x

## Installation

Install via Composer:

```bash
composer require vildanbina/hookshot
```

Publish configuration:

```bash
php artisan vendor:publish --tag=hookshot-config
```

Run migrations for database storage:

```bash
php artisan migrate
```

## Basic Usage

Apply middleware to routes:

```php
Route::middleware('track-requests')->group(function () {
    Route::apiResource('users', UserController::class);
});
```

Or apply globally to all routes in `bootstrap/app.php` (Laravel 11+):

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->append(\VildanBina\HookShot\Middleware\TrackRequestsMiddleware::class);
})
```

Retrieve tracked data:

```php
use VildanBina\HookShot\Facades\RequestTracker;

// Get recent requests
$requests = RequestTracker::get(50);

// Query with filters
$slowRequests = RequestTracker::query()
    ->where('execution_time', '>', 2.0)
    ->get();
```

## Features

**Storage Drivers:**

- Database: Full query support with Eloquent models
- Cache: In-memory storage with configurable TTL
- File: JSON or raw format file logging

**Filtering & Sampling:**

- Path exclusion patterns
- User agent filtering
- Configurable sampling rates
- Content-type based exclusions

**Performance Controls:**

- Request/response payload size limits
- Queue-based processing support
- Automatic data retention cleanup

**Security:**

- Sensitive header filtering
- Authentication data redaction
- Configurable data sanitization

## Configuration

All configuration options with their purposes:

```php
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
    'enabled' => env('HOOKSHOT_ENABLED', true),

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
```

## Usage

### Middleware Registration

**Manual Registration (Recommended)**

Apply to specific routes:

```php
Route::middleware('track-requests')->group(function () {
    Route::get('/api/users', [UserController::class, 'index']);
    Route::post('/api/users', [UserController::class, 'store']);
});
```

**Global Registration (Laravel 11+)**

Add to `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->append(\VildanBina\HookShot\Middleware\TrackRequestsMiddleware::class);
})
```

### Retrieving Tracked Data

```php
use VildanBina\HookShot\Facades\RequestTracker;

// Get all recent requests
$requests = RequestTracker::get(50);

// Find specific request by ID
$request = RequestTracker::find('uuid-here');

// Get requests with database driver (supports advanced queries)
$slowRequests = RequestTracker::query()
    ->where('execution_time', '>', 2.0)
    ->where('response_status', '>=', 400)
    ->get();

// Filter by date range
$todayRequests = RequestTracker::query()
    ->whereDate('timestamp', today())
    ->get();
```

### Event Integration

Listen to request capture events:

```php
use VildanBina\HookShot\Events\RequestCaptured;
use Illuminate\Support\Facades\Event;

Event::listen(RequestCaptured::class, function (RequestCaptured $event) {
    $request = $event->request;
    $requestData = $event->requestData;
    
    // Add custom metadata
    if ($request->user()) {
        $event->request->attributes->set('user_type', 'premium');
    }
});
```

## Commands

### Cleanup Old Data

Remove old tracking data based on retention settings:

```bash
# Clean using default settings
php artisan hookshot:cleanup

# Clean specific driver
php artisan hookshot:cleanup --driver=database

# Dry run to see what would be deleted
php artisan hookshot:cleanup --dry-run
```

## Storage Drivers

### Database Driver

- Best for queryable, persistent data
- Supports complex filtering and relationships
- Automatic cleanup via retention settings

### Cache Driver

- Fastest performance for temporary data
- Good for high-traffic applications
- Limited querying capabilities

### File Driver

- Good for development and debugging
- Human-readable storage format
- No database dependencies

## Captured Data Structure

Each tracked request includes:

```php
[
    'id' => 'uuid',
    'method' => 'POST',
    'url' => 'https://app.com/api/users',
    'path' => 'api/users',
    'headers' => ['accept' => ['application/json']],
    'payload' => ['name' => 'John', 'email' => 'john@example.com'],
    'ip' => '192.168.1.1',
    'user_agent' => 'Mozilla/5.0...',
    'user_id' => 123,
    'timestamp' => '2024-01-15T10:30:00Z',
    'execution_time' => 0.250,
    'response_status' => 201,
    'response_body' => ['id' => 456, 'name' => 'John'],
]
```

## Performance Tips

- **High Traffic**: Set `sampling_rate` to 0.1 (10%) or lower for production
- **Heavy Workloads**: Enable `use_queue` to process tracking asynchronously
- **Memory Management**: Limit `max_payload_size` and `max_response_size` based on your needs
- **Reduce Noise**: Use `excluded_paths` for health checks, admin routes, and static assets
- **Storage Optimization**: Configure `retention_days` for automatic cleanup of old data

## Contributing

See [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please e-mail vildanbina@gmail.com to report any security vulnerabilities instead of using the issue tracker.

## Credits

- [Vildan Bina](https://github.com/vildanbina)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
