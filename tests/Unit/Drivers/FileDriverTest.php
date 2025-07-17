<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use VildanBina\HookShot\Data\RequestData;
use VildanBina\HookShot\Drivers\FileDriver;

beforeEach(function () {
    $this->storagePath = storage_path('app/test-requests');

    // Clean up previous test data
    if (File::exists($this->storagePath)) {
        File::deleteDirectory($this->storagePath);
    }

    // Ensure the parent directory exists and is writable
    $parentDir = dirname($this->storagePath);
    if (! File::exists($parentDir)) {
        File::makeDirectory($parentDir, 0755, true);
    }

    $this->driver = new FileDriver([
        'path' => $this->storagePath,
        'format' => 'json',
        'retention_days' => 30,
    ]);
});

afterEach(function () {
    File::deleteDirectory($this->storagePath);
});

it('stores request data as JSON file', function () {
    $data = requestData(['timestamp' => now()->toISOString()]);
    $requestData = RequestData::fromArray($data);

    $result = $this->driver->store($requestData);

    expect($result)->toBeTrue();

    $expectedPath = $this->storagePath.'/'.now()->format('Y-m-d');
    $files = File::glob($expectedPath.'/'.$data['id'].'.json');

    expect($files)->toHaveCount(1);

    $content = File::get($files[0]);
    $stored = json_decode($content, true);

    expect($stored['method'])->toBe('POST')
        ->and($stored['payload'])->toBe(['name' => 'John', 'email' => 'john@example.com']);
});

it('stores request data in raw format', function () {
    $driver = new FileDriver([
        'path' => $this->storagePath,
        'format' => 'raw',
        'retention_days' => 30,
    ]);

    $data = requestData(['timestamp' => now()->toISOString()]);
    $requestData = RequestData::fromArray($data);

    $result = $driver->store($requestData);

    expect($result)->toBeTrue();

    $expectedPath = $this->storagePath.'/'.now()->format('Y-m-d');
    $files = File::glob($expectedPath.'/'.$data['id'].'.txt');

    expect($files)->toHaveCount(1);

    $content = File::get($files[0]);
    expect($content)->toContain('Method: POST')
        ->and($content)->toContain('URL: https://app.test/api/users')
        ->and($content)->toContain('"name": "John"');
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

it('returns null when file not found', function () {
    $found = $this->driver->find('non-existent-id');

    expect($found)->toBeNull();
});

it('gets multiple requests from files', function () {
    for ($i = 1; $i <= 3; $i++) {
        $data = requestData(['id' => "request-{$i}"]);
        $requestData = RequestData::fromArray($data);
        $this->driver->store($requestData);
    }

    $requests = $this->driver->get(10);

    expect($requests)->toHaveCount(3)
        ->and($requests->first())->toBeInstanceOf(RequestData::class);
});

it('deletes request files', function () {
    $data = requestData();
    $requestData = RequestData::fromArray($data);
    $this->driver->store($requestData);

    $result = $this->driver->delete($data['id']);

    expect($result)->toBeTrue();
    expect($this->driver->find($data['id']))->toBeNull();
});

it('handles storage errors gracefully', function () {
    // Try to store in a read-only location that doesn't exist and can't be created
    $brokenDriver = new FileDriver([
        'path' => '/proc/invalid/read-only-path',  // /proc is read-only in most systems
        'format' => 'json',
        'retention_days' => 30,
    ]);

    $data = requestData();
    $requestData = RequestData::fromArray($data);

    $result = $brokenDriver->store($requestData);

    expect($result)->toBeFalse();
});

it('cleans up old directories', function () {
    // Create an old directory (older than retention period)
    $oldPath = $this->storagePath.'/'.now()->subDays(45)->format('Y-m-d');
    File::makeDirectory($oldPath, 0755, true);
    File::put($oldPath.'/old-request.json', '{}');

    // Create recent directory
    $data = requestData(['timestamp' => now()->toISOString()]);
    $requestData = RequestData::fromArray($data);
    $this->driver->store($requestData);

    $deletedCount = $this->driver->cleanup();

    expect($deletedCount)->toBeGreaterThan(0);
    expect(File::exists($oldPath))->toBeFalse();
});

it('checks availability', function () {
    // Test that normal driver is available
    expect($this->driver->isAvailable())->toBeTrue();

    // Test with a non-writable path (using /proc which is read-only)
    $brokenDriver = new FileDriver([
        'path' => '/proc/read-only-path',
        'format' => 'json',
        'retention_days' => 30,
    ]);

    expect($brokenDriver->isAvailable())->toBeFalse();
});
