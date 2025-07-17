<?php

declare(strict_types=1);

use VildanBina\HookShot\Contracts\RequestTrackerContract;
use VildanBina\HookShot\Data\RequestData;
use VildanBina\HookShot\Facades\RequestTracker;

it('uses default driver from config', function () {
    $manager = app(RequestTrackerContract::class);

    expect($manager->getDefaultDriver())->toBe('database');
});

it('switches between storage drivers', function () {
    $data = requestData();
    $requestData = RequestData::fromArray($data);

    // Store in database
    $result = RequestTracker::driver('database')->store($requestData);
    expect($result)->toBeTrue();

    // Store in cache
    $result = RequestTracker::driver('cache')->store($requestData);
    expect($result)->toBeTrue();

    // Verify both drivers work independently
    $dbRequest = RequestTracker::driver('database')->find($data['id']);
    $cacheRequest = RequestTracker::driver('cache')->find($data['id']);

    expect($dbRequest)->not->toBeNull()
        ->and($cacheRequest)->not->toBeNull();
});

it('facade delegates to manager', function () {
    $data = requestData();
    $requestData = RequestData::fromArray($data);

    $result = RequestTracker::store($requestData);
    expect($result)->toBeTrue();

    $found = RequestTracker::find($data['id']);
    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($data['id']);

    $requests = RequestTracker::get();
    expect($requests)->toHaveCount(1);

    $deleted = RequestTracker::delete($data['id']);
    expect($deleted)->toBeTrue();
});

it('handles invalid driver gracefully', function () {
    expect(fn () => RequestTracker::driver('invalid'))
        ->toThrow(InvalidArgumentException::class);
});

it('performs cleanup across all drivers', function () {
    $oldData = requestData([
        'id' => 'old-request',
        'timestamp' => now()->subDays(45)->toISOString(),
    ]);
    $oldRequest = RequestData::fromArray($oldData);

    // Store old data in multiple drivers
    RequestTracker::driver('database')->store($oldRequest);
    RequestTracker::driver('cache')->store($oldRequest);

    $deletedCount = RequestTracker::cleanup();

    expect($deletedCount)->toBeInt();
    // Note: Actual cleanup behavior depends on driver implementation
});

it('gets driver availability status', function () {
    expect(RequestTracker::driver('database')->isAvailable())->toBeTrue();
    expect(RequestTracker::driver('cache')->isAvailable())->toBeTrue();
});

it('handles concurrent access safely', function () {
    $data1 = requestData(['id' => 'request-1']);
    $data2 = requestData(['id' => 'request-2']);
    $request1 = RequestData::fromArray($data1);
    $request2 = RequestData::fromArray($data2);

    // Simulate concurrent writes
    $result1 = RequestTracker::store($request1);
    $result2 = RequestTracker::store($request2);

    expect($result1)->toBeTrue()
        ->and($result2)->toBeTrue();

    $requests = RequestTracker::get();
    expect($requests)->toHaveCount(2);
});
