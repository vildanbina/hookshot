<?php

declare(strict_types=1);

use VildanBina\HookShot\Data\RequestData;
use VildanBina\HookShot\Facades\RequestTracker;

it('cleans up old requests via command using default driver', function () {
    $oldData = requestData([
        'id' => 'old-request',
        'timestamp' => now()->subDays(45)->toISOString(),
    ]);
    $oldRequest = RequestData::fromArray($oldData);
    RequestTracker::store($oldRequest);

    $recentData = requestData([
        'id' => 'recent-request',
        'timestamp' => now()->toISOString(),
    ]);
    $recentRequest = RequestData::fromArray($recentData);
    RequestTracker::store($recentRequest);

    $this->artisan('hookshot:cleanup')
        ->expectsOutput('Starting request tracker cleanup...')
        ->assertExitCode(0);

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

    $this->artisan('hookshot:cleanup --dry-run')
        ->expectsOutput('DRY RUN MODE - No data will be deleted')
        ->assertExitCode(0);

    expect(RequestTracker::find('old-request'))->not->toBeNull();
});

it('cleans up specific driver only', function () {
    $oldData = requestData([
        'id' => 'old-request',
        'timestamp' => now()->subDays(45)->toISOString(),
    ]);
    $oldRequest = RequestData::fromArray($oldData);

    RequestTracker::driver('database')->store($oldRequest);
    RequestTracker::driver('cache')->store($oldRequest);

    $this->artisan('hookshot:cleanup --driver=database')
        ->assertExitCode(0);

    expect(RequestTracker::driver('database')->find('old-request'))->toBeNull();
});

it('handles invalid driver gracefully', function () {
    $this->artisan('hookshot:cleanup --driver=invalid')
        ->expectsOutput("Cleanup failed: Driver 'invalid' is not configured.")
        ->assertExitCode(1);
});

it('respects configured default driver', function () {
    config(['hookshot.default' => 'cache']);

    $oldData = requestData([
        'id' => 'old-request',
        'timestamp' => now()->subDays(45)->toISOString(),
    ]);
    $oldRequest = RequestData::fromArray($oldData);

    RequestTracker::driver('database')->store($oldRequest);
    RequestTracker::driver('cache')->store($oldRequest);

    $this->artisan('hookshot:cleanup')
        ->assertExitCode(0);

    expect(RequestTracker::driver('database')->find('old-request'))->not->toBeNull();
});

it('handles cleanup errors gracefully', function () {
    $this->artisan('hookshot:cleanup')
        ->expectsOutput('Starting request tracker cleanup...');
});
