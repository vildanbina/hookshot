<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use VildanBina\HookShot\Facades\RequestTracker;

beforeEach(function () {
    Route::post('/api/test', fn () => response()->json(['success' => true]));
});

it('respects sampling rate configuration', function () {
    config(['request-tracker.sampling_rate' => 0.0]); // Track 0% of requests

    // Make multiple requests
    for ($i = 0; $i < 10; $i++) {
        $this->postJson('/api/test', ['data' => $i]);
    }

    $requests = RequestTracker::get();
    expect($requests)->toHaveCount(0); // No requests should be tracked
});

it('tracks all requests with 100% sampling', function () {
    config(['request-tracker.sampling_rate' => 1.0]); // Track 100% of requests

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/test', ['data' => $i]);
    }

    $requests = RequestTracker::get();
    expect($requests)->toHaveCount(5); // All requests should be tracked
});

it('queues storage when configured', function () {
    config(['request-tracker.use_queue' => true]);
    Queue::fake();

    $this->postJson('/api/test', ['data' => 'test']);

    // Storage should be queued, not executed immediately
    Queue::assertPushed(VildanBina\HookShot\Jobs\StoreRequestDataJob::class);
});

it('stores immediately when queue is disabled', function () {
    config(['request-tracker.use_queue' => false]);

    $this->postJson('/api/test', ['data' => 'test']);

    // Request should be stored immediately
    $requests = RequestTracker::get();
    expect($requests)->toHaveCount(1);
});

it('handles high request volume efficiently', function () {
    $startTime = microtime(true);

    // Simulate high volume
    for ($i = 0; $i < 50; $i++) {
        $this->postJson('/api/test', ['data' => $i]);
    }

    $endTime = microtime(true);
    $duration = $endTime - $startTime;

    // Should handle 50 requests reasonably fast (adjust threshold as needed)
    expect($duration)->toBeLessThan(5.0); // 5 seconds max

    $requests = RequestTracker::get();
    expect($requests)->toHaveCount(50);
});

it('limits memory usage with payload size limits', function () {
    config(['request-tracker.max_payload_size' => 1024]); // 1KB limit

    $largeData = str_repeat('x', 10000); // 10KB payload

    $this->postJson('/api/test', ['large_field' => $largeData]);

    $requests = RequestTracker::get();
    $request = $requests->first();

    // Payload should be truncated
    expect($request->payload)->toHaveKey('_truncated')
        ->and($request->payload['_truncated'])->toBeTrue();
});

it('handles concurrent requests safely', function () {
    $responses = [];

    // Simulate concurrent requests (as much as possible in single-threaded tests)
    for ($i = 0; $i < 10; $i++) {
        $responses[] = $this->postJson('/api/test', ['request_id' => $i]);
    }

    foreach ($responses as $response) {
        $response->assertStatus(200);
    }

    $requests = RequestTracker::get();
    expect($requests)->toHaveCount(10);

    // All requests should have unique IDs
    $ids = $requests->pluck('id')->toArray();
    expect($ids)->toHaveCount(10)
        ->and(array_unique($ids))->toHaveCount(10);
});

it('measures execution time accurately', function () {
    Route::post('/slow-endpoint', function () {
        usleep(100000); // 100ms delay

        return response()->json(['slow' => true]);
    });

    $this->postJson('/slow-endpoint');

    $requests = RequestTracker::get();
    $request = $requests->first();

    expect($request->executionTime)->toBeFloat()
        ->and($request->executionTime)->toBeGreaterThan(0.05) // At least 50ms
        ->and($request->executionTime)->toBeLessThan(1.0); // Less than 1 second
});

it('handles errors without impacting application performance', function () {
    // Mock storage failure
    RequestTracker::shouldReceive('store')->andThrow(new Exception('Storage failed'));

    $response = $this->postJson('/api/test', ['data' => 'test']);

    // Application should continue working normally
    $response->assertStatus(200)
        ->assertJson(['success' => true]);
});
