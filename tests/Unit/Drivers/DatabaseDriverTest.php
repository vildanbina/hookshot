<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use VildanBina\HookShot\Data\RequestData;
use VildanBina\HookShot\Drivers\DatabaseDriver;

beforeEach(function () {
    // Clean database before each test
    DB::table('request_tracker_logs')->truncate();

    $this->driver = new DatabaseDriver(app('db'), [
        'table' => 'request_tracker_logs',
        'retention_days' => 30,
    ]);
});

it('stores request data in database', function () {
    $data = requestData();
    $requestData = RequestData::fromArray($data);

    $result = $this->driver->store($requestData);

    expect($result)->toBeTrue();

    $record = DB::table('request_tracker_logs')->where('id', $data['id'])->first();
    expect($record)->not->toBeNull()
        ->and($record->method)->toBe('POST')
        ->and($record->url)->toBe('https://app.test/api/users')
        ->and(json_decode($record->payload, true))->toBe(['name' => 'John', 'email' => 'john@example.com']);
});

it('finds request data by ID', function () {
    $data = requestData();
    $requestData = RequestData::fromArray($data);
    $this->driver->store($requestData);

    $found = $this->driver->find($data['id']);

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($data['id'])
        ->and($found->method)->toBe('POST')
        ->and($found->payload)->toBe(['name' => 'John', 'email' => 'john@example.com']);
});

it('returns null when request not found', function () {
    $found = $this->driver->find('non-existent-id');

    expect($found)->toBeNull();
});

it('gets multiple requests with limit', function () {
    // Store multiple requests
    for ($i = 1; $i <= 5; $i++) {
        $data = requestData(['id' => "request-{$i}"]);
        $requestData = RequestData::fromArray($data);
        $this->driver->store($requestData);
    }

    $requests = $this->driver->get([], 3);

    expect($requests)->toHaveCount(3)
        ->and($requests->first())->toBeInstanceOf(RequestData::class);
});

it('deletes request data', function () {
    $data = requestData();
    $requestData = RequestData::fromArray($data);
    $this->driver->store($requestData);

    $result = $this->driver->delete($data['id']);

    expect($result)->toBeTrue();

    $record = DB::table('request_tracker_logs')->where('id', $data['id'])->first();
    expect($record)->toBeNull();
});

it('handles database errors gracefully', function () {
    // Mock a database error by using invalid table
    $brokenDriver = new DatabaseDriver(app('db'), ['table' => 'non_existent_table']);
    $data = requestData();
    $requestData = RequestData::fromArray($data);

    $result = $brokenDriver->store($requestData);

    expect($result)->toBeFalse();
});

it('cleans up old records', function () {
    // Create old record
    $oldData = requestData([
        'id' => 'old-request',
        'timestamp' => now()->subDays(45)->toISOString(),
    ]);
    $oldRequest = RequestData::fromArray($oldData);
    $this->driver->store($oldRequest);

    // Create recent record with current timestamp
    $recentData = requestData([
        'id' => 'recent-request',
        'timestamp' => now()->toISOString(),
    ]);
    $recentRequest = RequestData::fromArray($recentData);
    $this->driver->store($recentRequest);

    $deletedCount = $this->driver->cleanup();

    expect($deletedCount)->toBe(1);
    expect($this->driver->find('old-request'))->toBeNull();
    expect($this->driver->find('recent-request'))->not->toBeNull();
});
