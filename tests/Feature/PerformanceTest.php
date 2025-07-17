<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use VildanBina\HookShot\Facades\RequestTracker;

beforeEach(function () {
    Route::post('/api/test', fn () => response()->json(['success' => true]))->middleware('track-requests');
});

it('respects sampling rate configuration', function () {
    config(['hookshot.sampling_rate' => 0.0]); // Track 0% of requests

    // Make multiple requests
    for ($i = 0; $i < 10; $i++) {
        $this->postJson('/api/test', ['data' => $i]);
    }

    $requests = RequestTracker::get();
    expect($requests)->toHaveCount(0); // No requests should be tracked
});

it('tracks all requests with 100% sampling', function () {
    config(['hookshot.sampling_rate' => 1.0]); // Track 100% of requests

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/test', ['data' => $i]);
    }

    $requests = RequestTracker::get();
    expect($requests)->toHaveCount(5); // All requests should be tracked
});

it('queues storage when configured', function () {
    config(['hookshot.use_queue' => true]);
    Queue::fake();

    $this->postJson('/api/test', ['data' => 'test']);

    // Storage should be queued, not executed immediately
    Queue::assertPushed(VildanBina\HookShot\Jobs\StoreRequestDataJob::class);
});

it('stores immediately when queue is disabled', function () {
    config(['hookshot.use_queue' => false]);

    $this->postJson('/api/test', ['data' => 'test']);

    // Request should be stored immediately
    $requests = RequestTracker::get();
    expect($requests)->toHaveCount(1);
});

it('handles high request volume efficiently', function () {
    $startTime = microtime(true);

    // Simulate 50 concurrent requests
    for ($i = 0; $i < 50; $i++) {
        $this->postJson('/api/test', ['data' => $i]);
    }

    $duration = microtime(true) - $startTime;

    // Should handle 50 requests reasonably fast (adjust threshold as needed)
    expect($duration)->toBeLessThan(5.0); // 5 seconds max

    $requests = RequestTracker::get();
    expect($requests)->toHaveCount(50);
});

it('limits payload size based on configuration', function () {
    config(['hookshot.max_payload_size' => 1024]); // 1KB limit

    $largePayload = str_repeat('x', 2048); // 2KB payload
    $this->postJson('/api/test', ['data' => $largePayload]);

    $requests = RequestTracker::get();
    $request = $requests->first();

    // Payload should be truncated or limited
    expect(mb_strlen(json_encode($request->payload)))->toBeLessThan(1024);
});

it('handles concurrent requests safely', function () {
    $responses = [];

    // Simulate multiple concurrent requests
    for ($i = 0; $i < 10; $i++) {
        $response = $this->postJson('/api/test', ['request_id' => $i]);
        $responses[] = $response;
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
        usleep(50000); // 50ms delay

        return response()->json(['delayed' => true]);
    })->middleware('track-requests');

    $this->postJson('/slow-endpoint', ['data' => 'test']);

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
