# HookShot

**A powerful Laravel package for comprehensive HTTP request tracking and analytics.**

HookShot automatically captures every incoming HTTP request to your Laravel application, storing detailed information for debugging, analytics, compliance, and monitoring purposes. With multiple storage drivers and extensive configuration options, it adapts to any use case from development debugging to enterprise compliance logging.

## Why HookShot?

- **🔍 Debug Issues** - Reproduce bugs by seeing exactly what requests caused them
- **📊 Analytics** - Track API usage patterns, popular endpoints, and user behavior
- **🛡️ Security** - Monitor suspicious requests and track authentication attempts
- **📋 Compliance** - Meet audit requirements with comprehensive request logging
- **⚡ Performance** - Identify slow endpoints and optimize request handling
- **🔌 API Monitoring** - Track external API integrations and webhook deliveries

## Installation

```bash
composer require vildanbina/hookshot
```

The package will automatically register itself. Run migrations to set up database storage:

```bash
php artisan migrate
```

## Quick Start

HookShot starts tracking requests immediately after installation. View captured requests:

```php
use VildanBina\HookShot\Facades\RequestTracker;

// Get recent requests
$requests = RequestTracker::get();

// Find specific request by ID
$request = RequestTracker::find('550e8400-e29b-41d4-a716-446655440000');

// Search requests
$apiRequests = RequestTracker::get(['path' => 'api/*'], limit: 50);
```

## Storage Options

### Database Storage (Default)

Perfect for production environments requiring queryability and persistence.

```php
// config/request-tracker.php
'drivers' => [
    'database' => [
        'connection' => 'mysql',           // Use specific DB connection
        'table' => 'request_tracker_logs', // Custom table name
        'retention_days' => 30,            // Auto-cleanup after 30 days
    ],
],
```

**Benefits:**

- Full SQL querying capabilities
- Indexed for fast searches by timestamp, user, status
- Persistent storage with backup support
- Great for analytics and reporting

### Cache Storage

Ideal for high-traffic applications needing fast access to recent requests.

```php
'drivers' => [
    'cache' => [
        'store' => 'redis',         // Use specific cache store
        'prefix' => 'requests',     // Key prefix
        'retention_days' => 7,      // TTL-based cleanup
    ],
],
```

**Benefits:**

- Lightning-fast storage and retrieval
- Automatic TTL-based cleanup
- Memory-efficient for recent data
- Perfect for debugging recent issues

### File Storage

Great for development, testing, or when you need human-readable request logs.

```php
'drivers' => [
    'file' => [
        'path' => storage_path('app/requests'),
        'format' => 'json',  // 'json' or 'raw'
        'retention_days' => 30,
    ],
],
```

**Benefits:**

- Human-readable request dumps
- No database required
- Easy to share or archive
- Organized by date: `2024-01-15/request-uuid.json`

**Raw Format Example:**

```
Method: POST
URL: https://app.com/api/users
Path: api/users
IP: 192.168.1.100
User Agent: Mozilla/5.0...
User ID: 12345

Headers:
  accept: application/json
  authorization: [FILTERED]
  content-type: application/json

Payload:
{
  "name": "John Doe",
  "email": "john@example.com"
}
```

## Configuration

### Smart Filtering

Exclude unnecessary requests to keep your logs clean:

```php
// config/request-tracker.php
return [
    // Exclude health checks, admin tools, etc.
    'excluded_paths' => [
        'health-check',
        'up',
        '_debugbar/*',
        'telescope/*',
        'horizon/*',
        'pulse/*',
        'admin/logs/*',
    ],

    // Exclude monitoring bots
    'excluded_user_agents' => [
        'pingdom',
        'uptimerobot',
        'googlebot',
        'bingbot',
    ],

    // Performance optimization
    'sampling_rate' => 0.1,  // Track only 10% of requests
];
```

### Performance Controls

Optimize for your environment:

```php
return [
    // Payload size limits
    'max_payload_size' => 65536,   // 64KB - larger payloads truncated
    'max_response_size' => 10240,  // 10KB - larger responses truncated

    // Queue processing for high-traffic apps
    'use_queue' => true,
];
```

### Security & Privacy

Sensitive data is automatically filtered:

```php
// These headers are automatically replaced with [FILTERED]
'authorization', 'cookie', 'set-cookie', 'x-api-key', 'x-auth-token'
```

## Data Structure

Each tracked request captures comprehensive information:

```php
[
    'id' => '550e8400-e29b-41d4-a716-446655440000',
    'method' => 'POST',
    'url' => 'https://app.com/api/users',
    'path' => 'api/users',
    'headers' => [
        'accept' => ['application/json'],
        'authorization' => ['[FILTERED]'],
        'user-agent' => ['PostmanRuntime/7.28.4'],
    ],
    'query' => ['page' => '1', 'limit' => '20'],
    'payload' => ['name' => 'John', 'email' => 'john@example.com'],
    'ip' => '192.168.1.100',
    'user_agent' => 'PostmanRuntime/7.28.4',
    'user_id' => 12345,
    'metadata' => [
        'route_name' => 'users.store',
        'route_action' => 'UserController@store',
        'session_id' => 'abc123...',
        'referer' => 'https://app.com/dashboard',
        'content_type' => 'application/json',
    ],
    'timestamp' => '2024-01-15T10:30:00Z',
    'execution_time' => 0.245,  // seconds
    'response_status' => 201,
    'response_headers' => ['content-type' => ['application/json']],
    'response_body' => ['id' => 67890, 'name' => 'John', 'created_at' => '...'],
]
```

## Querying Requests

### Basic Retrieval

```php
use VildanBina\HookShot\Facades\RequestTracker;

// Get latest 100 requests
$requests = RequestTracker::get();

// Get latest 50 requests
$requests = RequestTracker::get([], 50);

// Find specific request
$request = RequestTracker::find('550e8400-e29b-41d4-a716-446655440000');
```

### Using Different Storage Drivers

```php
use VildanBina\HookShot\Contracts\RequestTrackerContract;

$tracker = app(RequestTrackerContract::class);

// Query cache storage
$recentRequests = $tracker->driver('cache')->get();

// Query file storage
$fileRequests = $tracker->driver('file')->get();

// Query specific database connection
$dbRequests = $tracker->driver('database')->get();
```

### Manual Storage

Store custom request data:

```php
use VildanBina\HookShot\Data\RequestData;
use VildanBina\HookShot\Support\DataExtractor;

$extractor = new DataExtractor(config('request-tracker'));
$requestData = RequestData::fromRequest($request, $extractor);

RequestTracker::store($requestData);
```

## Extending with Events

Add custom data to any request using Laravel events:

```php
use VildanBina\HookShot\Events\RequestCaptured;
use Illuminate\Support\Facades\Event;

Event::listen(RequestCaptured::class, function (RequestCaptured $event) {
    $request = $event->request;

    // Add custom metadata during request execution
    if ($request->user()) {
        $request->attributes->set('user_tier', $request->user()->tier);
        $request->attributes->set('subscription_active', $request->user()->subscribed());
    }

    // Add feature flags
    $request->attributes->set('feature_flags', [
        'new_checkout' => config('features.new_checkout'),
        'beta_ui' => session('beta_ui_enabled'),
    ]);

    // Add business context
    if ($request->routeIs('api.orders.*')) {
        $request->attributes->set('order_value', $request->input('total'));
        $request->attributes->set('payment_method', $request->input('payment.method'));
    }
});
```

Register the listener in your `EventServiceProvider`:

```php
protected $listen = [
    \VildanBina\HookShot\Events\RequestCaptured::class => [
        \App\Listeners\AddCustomRequestData::class,
    ],
];
```

## Management Commands

### Cleanup Old Data

```bash
# Clean up all drivers based on retention_days config
php artisan request-tracker:cleanup

# Clean specific driver
php artisan request-tracker:cleanup --driver=database

# See what would be deleted without actually deleting
php artisan request-tracker:cleanup --dry-run
```

### Publishing Configuration

```bash
# Publish config file for customization
php artisan vendor:publish --tag=request-tracker-config

# Publish migrations for customization
php artisan vendor:publish --tag=request-tracker-migrations
```

## Environment Configuration

Use environment variables for easy deployment:

```env
# Enable/disable tracking
REQUEST_TRACKER_ENABLED=true

# Default storage driver
REQUEST_TRACKER_DRIVER=database

# Database settings
REQUEST_TRACKER_DB_CONNECTION=mysql

# Performance settings
REQUEST_TRACKER_SAMPLING_RATE=1.0
REQUEST_TRACKER_USE_QUEUE=false

# Cache settings
REQUEST_TRACKER_CACHE_STORE=redis
```

## Use Cases

### API Debugging

```php
// Find all failed API requests
$errors = RequestTracker::get(['path' => 'api/*', 'status' => 500]);

// Track specific user's requests
$userRequests = RequestTracker::get(['user_id' => 12345]);
```

### Security Monitoring

```php
// Monitor authentication attempts
Event::listen(RequestCaptured::class, function ($event) {
    if ($event->request->routeIs('auth.*')) {
        $event->request->attributes->set('auth_attempt', true);
        $event->request->attributes->set('login_method', $event->request->input('method'));
    }
});
```

### Performance Analysis

```php
// Find slow requests
$slowRequests = collect(RequestTracker::get())
    ->filter(fn($req) => $req->execution_time > 2.0);

// Track API endpoint usage
$apiStats = collect(RequestTracker::get(['path' => 'api/*']))
    ->groupBy('path')
    ->map(fn($requests) => $requests->count());
```

### Compliance Logging

```php
// Track all data access
Event::listen(RequestCaptured::class, function ($event) {
    if ($event->request->routeIs('admin.*') || $event->request->routeIs('api.users.*')) {
        $event->request->attributes->set('data_access', true);
        $event->request->attributes->set('admin_user', $event->request->user()?->email);
    }
});
```

## Testing

Disable tracking in tests:

```php
// In phpunit.xml
<env name="REQUEST_TRACKER_ENABLED" value="false"/>
```

Or exclude test environment:

```php
// config/request-tracker.php
'excluded_environments' => ['testing'],
```

## Requirements

- **PHP:** 8.1 or higher
- **Laravel:** 10.0 or 11.0
- **Storage:** Database, Cache, or File system access

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Contributing

Contributions are welcome! Please see our [Contributing Guidelines](CONTRIBUTING.md) for details.

## Support

- **Issues:** [GitHub Issues](https://github.com/vildanbina/hookshot/issues)
- **Documentation:** [GitHub Wiki](https://github.com/vildanbina/hookshot/wiki)
- **Email:** [vildanbina@gmail.com](mailto:vildanbina@gmail.com)
