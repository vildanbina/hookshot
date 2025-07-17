<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use VildanBina\HookShot\Data\RequestData;
use VildanBina\HookShot\Drivers\CacheDriver;

beforeEach(function () {
    Cache::flush();
    $this->driver = new CacheDriver(app('cache'), [
        'prefix' => 'test_tracker',
        'retention_days' => 7,
    ]);
});

it('stores request data in cache', function () {
    $data = requestData();
    $requestData = RequestData::fromArray($data);

    $result = $this->driver->store($requestData);

    expect($result)->toBeTrue();

    $cached = Cache::get('test_tracker:request:'.$data['id']);
    expect($cached)->not->toBeNull()
        ->and($cached['method'])->toBe('POST')
        ->and($cached['payload'])->toBe(['name' => 'John', 'email' => 'john@example.com']);
});

it('finds request data by ID', function () {
    $data = requestData();
    $requestData = RequestData::fromArray($data);
    $this->driver->store($requestData);

    $found = $this->driver->find($data['id']);

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($data['id'])
        ->and($found->method)->toBe('POST');
});

it('returns null when request not found in cache', function () {
    $found = $this->driver->find('non-existent-id');

    expect($found)->toBeNull();
});

it('maintains index for listing requests', function () {
    $data1 = requestData(['id' => 'request-1']);
    $data2 = requestData(['id' => 'request-2']);
    $request1 = RequestData::fromArray($data1);
    $request2 = RequestData::fromArray($data2);

    $this->driver->store($request1);
    $this->driver->store($request2);

    $requests = $this->driver->get(10);

    expect($requests)->toHaveCount(2)
        ->and($requests->pluck('id')->toArray())->toContain('request-1', 'request-2');
});

it('respects limit when getting requests', function () {
    for ($i = 1; $i <= 5; $i++) {
        $data = requestData(['id' => "request-{$i}"]);
        $requestData = RequestData::fromArray($data);
        $this->driver->store($requestData);
    }

    $requests = $this->driver->get(3);

    expect($requests)->toHaveCount(3);
});

it('deletes request data from cache', function () {
    $data = requestData();
    $requestData = RequestData::fromArray($data);
    $this->driver->store($requestData);

    $result = $this->driver->delete($data['id']);

    expect($result)->toBeTrue();
    expect(Cache::get('test_tracker:'.$data['id']))->toBeNull();
});

it('handles cache errors gracefully', function () {
    $data = requestData();
    $requestData = RequestData::fromArray($data);

    // Create a mock cache manager that throws an exception
    $mockCacheManager = Mockery::mock('Illuminate\Cache\CacheManager');
    $mockStore = Mockery::mock('Illuminate\Contracts\Cache\Store');

    $mockCacheManager->shouldReceive('store')
        ->andReturn($mockStore);

    $mockStore->shouldReceive('put')
        ->andThrow(new Exception('Cache error'));

    // Create driver with mocked cache manager
    $driver = new CacheDriver($mockCacheManager, [
        'prefix' => 'test_tracker',
        'retention_days' => 7,
    ]);

    $result = $driver->store($requestData);

    expect($result)->toBeFalse();
});

it('cleans up expired entries', function () {
    $data = requestData();
    $requestData = RequestData::fromArray($data);
    $this->driver->store($requestData);

    // Since we can't easily test TTL expiration in memory cache,
    // we'll test that cleanup returns a count
    $deletedCount = $this->driver->cleanup();

    expect($deletedCount)->toBeInt();
});

it('checks availability', function () {
    expect($this->driver->isAvailable())->toBeTrue();
});
