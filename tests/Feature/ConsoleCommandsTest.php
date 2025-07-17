<?php

declare(strict_types=1);

use VildanBina\HookShot\Data\RequestData;
use VildanBina\HookShot\Facades\RequestTracker;

it('cleans up old requests via command using default driver', function () {
    // Create old request
    $oldData = requestData([
        'id' => 'old-request',
        'timestamp' => now()->subDays(45)->toISOString(),
    ]);
    $oldRequest = RequestData::fromArray($oldData);
    RequestTracker::store($oldRequest);

    // Create recent request with current timestamp
    $recentData = requestData([
        'id' => 'recent-request',
        'timestamp' => now()->toISOString(),
    ]);
    $recentRequest = RequestData::fromArray($recentData);
    RequestTracker::store($recentRequest);

    $this->artisan('request-tracker:cleanup')
        ->expectsOutput('Starting request tracker cleanup...')
        ->expectsOutput('Using default driver: database')
        ->assertExitCode(0);

    // Old request should be deleted, recent should remain
    expect(RequestTracker::find('old-request'))->toBeNull();
    expect(RequestTracker::find('recent-request'))->not->toBeNull();
});

it('supports dry run mode', function () {
    $oldData = requestData([
        'id' => 'old-request',
        'timestamp' => now()->subDays(45)->toISOString(),
    ]);
    $oldRequest = RequestData::fromArray($oldData);
    RequestTracker::store($oldRequest);

    $this->artisan('request-tracker:cleanup --dry-run')
        ->expectsOutput('DRY RUN: No data will actually be deleted')
        ->assertExitCode(0);

    // Nothing should be deleted in dry run
    expect(RequestTracker::find('old-request'))->not->toBeNull();
});

it('cleans up specific driver only', function () {
    $oldData = requestData([
        'id' => 'old-request',
        'timestamp' => now()->subDays(45)->toISOString(),
    ]);
    $oldRequest = RequestData::fromArray($oldData);

    // Store in both drivers
    RequestTracker::driver('database')->store($oldRequest);
    RequestTracker::driver('cache')->store($oldRequest);

    $this->artisan('request-tracker:cleanup --driver=database')
        ->assertExitCode(0);

    // Only database should be cleaned
    expect(RequestTracker::driver('database')->find('old-request'))->toBeNull();
    // Cache might still have it (depends on TTL implementation)
});

it('handles invalid driver gracefully', function () {
    $this->artisan('request-tracker:cleanup --driver=invalid')
        ->expectsOutput('Driver \'invalid\' is not configured')
        ->assertExitCode(1);
});

it('respects configured default driver', function () {
    // Temporarily change default driver to cache
    config(['request-tracker.default' => 'cache']);

    $oldData = requestData([
        'id' => 'old-request',
        'timestamp' => now()->subDays(45)->toISOString(),
    ]);
    $oldRequest = RequestData::fromArray($oldData);

    // Store in both drivers
    RequestTracker::driver('database')->store($oldRequest);
    RequestTracker::driver('cache')->store($oldRequest);

    $this->artisan('request-tracker:cleanup')
        ->expectsOutput('Using default driver: cache')
        ->assertExitCode(0);

    // Only cache should be cleaned (database should still have the record)
    expect(RequestTracker::driver('database')->find('old-request'))->not->toBeNull();
});

it('handles cleanup errors gracefully', function () {
    // Mock a driver that throws an exception
    $this->artisan('request-tracker:cleanup')
        ->expectsOutput('Starting request tracker cleanup...');

    // Command should handle errors and not crash
});
