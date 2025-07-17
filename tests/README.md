# HookShot Test Suite

This directory contains comprehensive Pest tests for the HookShot request tracking package.

## Test Structure

### Unit Tests (`tests/Unit/`)

Individual component testing with isolated dependencies:

- **`RequestDataTest.php`** - Data structure creation, transformation, and immutability
- **`RequestFilterTest.php`** - Filtering logic (sampling, exclusions, path matching)
- **`DataExtractorTest.php`** - Request data extraction and sensitive data filtering
- **`ResponseCaptureTest.php`** - Response data capture and processing
- **`EventsTest.php`** - Event creation and dispatching
- **`Drivers/`** - Storage driver functionality:
  - `DatabaseDriverTest.php` - Database storage and retrieval
  - `CacheDriverTest.php` - Cache-based storage with TTL
  - `FileDriverTest.php` - File storage in JSON/raw formats

### Feature Tests (`tests/Feature/`)

End-to-end behavior testing with full Laravel integration:

- **`RequestTrackingTest.php`** - Complete request tracking flow
- **`RequestTrackerManagerTest.php`** - Driver management and facade API
- **`ConsoleCommandsTest.php`** - Artisan command functionality
- **`SecurityTest.php`** - Security features and data filtering
- **`PerformanceTest.php`** - Performance controls and optimization

## Test Helpers

### `Pest.php`

- Global test configuration
- Shared helper functions:
  - `requestData()` - Generate test request data
  - `mockRequest()` - Create mock HTTP requests

### `TestCase.php`

- Package service provider registration
- Test database configuration (SQLite in-memory)
- HookShot configuration setup

## Key Test Scenarios

### ✅ Core Functionality

- [x] Request data capture and storage
- [x] All storage drivers (Database, Cache, File)
- [x] Event system and extensibility
- [x] Facade and manager API

### ✅ Security Features

- [x] Sensitive header filtering (`[FILTERED]` markers)
- [x] Payload size limits
- [x] Path exclusions
- [x] User agent filtering
- [x] File upload handling

### ✅ Performance Features

- [x] Sampling rate configuration
- [x] Queue processing
- [x] Memory usage control
- [x] Execution time measurement
- [x] High-volume request handling

### ✅ Error Handling

- [x] Storage failures
- [x] Invalid configurations
- [x] Malformed data
- [x] Graceful degradation

## Running Tests

```bash
# Run all tests
vendor/bin/pest

# Run specific test suite
vendor/bin/pest tests/Unit/
vendor/bin/pest tests/Feature/

# Run specific test file
vendor/bin/pest tests/Unit/RequestDataTest.php

# Run with coverage
vendor/bin/pest --coverage

# Run with verbose output
vendor/bin/pest --verbose
```

## Test Configuration

Tests use:

- **Database**: SQLite in-memory for fast execution
- **Cache**: Array driver for predictable behavior
- **File Storage**: Temporary test directories (auto-cleaned)
- **Queue**: Fake queue driver for testing
- **Events**: Faked for isolation

## Testing Philosophy

### Focused & Readable

- Each test has a single, clear purpose
- Descriptive test names using `it()` syntax
- Minimal setup with shared helpers

### Comprehensive Coverage

- All public APIs tested
- Core business logic validated
- Error scenarios handled
- Integration points verified

### DRY Principles

- Shared helpers for common operations
- Consistent test data patterns
- Reusable mock objects
- Global configuration in `Pest.php`

## Example Test Structure

```php
it('filters sensitive headers', function () {
    $extractor = new DataExtractor([]);

    $headers = [
        'authorization' => ['Bearer secret'],
        'user-agent' => ['TestAgent/1.0'],
    ];

    $filtered = $extractor->filterHeaders($headers);

    expect($filtered['authorization'])->toBe(['[FILTERED]'])
        ->and($filtered['user-agent'])->toBe(['TestAgent/1.0']);
});
```

This approach ensures:

- **Clear intent** - What behavior is being tested
- **Minimal setup** - Only necessary data
- **Focused assertions** - Specific expected outcomes
- **Readable expectations** - Natural language assertions
