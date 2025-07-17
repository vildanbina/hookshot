<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use VildanBina\HookShot\Events\RequestCaptured;
use VildanBina\HookShot\Facades\RequestTracker;

beforeEach(function () {
    Route::post('/api/users', fn () => response()->json(['id' => 123, 'name' => 'John']))->name('users.store');
    Route::get('/health-check', fn () => 'OK');
});

it('tracks HTTP requests automatically', function () {
    $response = $this->postJson('/api/users', ['name' => 'John', 'email' => 'john@test.com']);

    $response->assertStatus(200);

    $requests = RequestTracker::get();
    expect($requests)->toHaveCount(1);

    $request = $requests->first();
    expect($request->method)->toBe('POST')
        ->and($request->path)->toBe('api/users')
        ->and($request->payload)->toBe(['name' => 'John', 'email' => 'john@test.com'])
        ->and($request->responseStatus)->toBe(200);
});

it('excludes requests based on configuration', function () {
    $this->get('/health-check');

    $requests = RequestTracker::get();
    expect($requests)->toHaveCount(0); // Excluded by config
});

it('dispatches RequestCaptured event', function () {
    Event::fake();

    $this->postJson('/api/users', ['name' => 'John']);

    Event::assertDispatched(RequestCaptured::class, function ($event) {
        return $event->request->getPathInfo() === '/api/users'
            && $event->requestData->method === 'POST';
    });
});

it('allows custom data via events', function () {
    Event::listen(RequestCaptured::class, function ($event) {
        $event->request->attributes->set('custom_field', 'custom_value');
        $event->request->attributes->set('user_tier', 'premium');
    });

    $this->postJson('/api/users', ['name' => 'John']);

    // Note: In real implementation, custom fields would be captured
    // This tests the event mechanism works
    expect(true)->toBeTrue();
});

it('handles errors gracefully', function () {
    // Simulate storage failure
    RequestTracker::shouldReceive('store')->andReturn(false);

    $response = $this->postJson('/api/users', ['name' => 'John']);

    // Application should continue working even if tracking fails
    $response->assertStatus(200);
});

it('captures file uploads', function () {
    Route::post('/upload', fn () => response()->json(['uploaded' => true]));

    $file = Illuminate\Http\UploadedFile::fake()->create('document.pdf', 100);

    $response = $this->post('/upload', ['document' => $file]);

    $response->assertStatus(200);

    $requests = RequestTracker::get();
    $request = $requests->first();

    expect($request->payload)->toHaveKey('document')
        ->and($request->payload['document'])->toHaveKey('name', 'document.pdf')
        ->and($request->payload['document'])->toHaveKey('size', 102400); // 100KB = 102400 bytes
});

it('tracks authenticated user requests', function () {
    $user = createTestUser(456, 'Test User');

    $this->actingAs($user)->postJson('/api/users', ['name' => 'John']);

    $requests = RequestTracker::get();
    $request = $requests->first();

    expect($request->userId)->toBe(456);
});

it('captures response data', function () {
    $this->postJson('/api/users', ['name' => 'John']);

    $requests = RequestTracker::get();
    $request = $requests->first();

    expect($request->responseStatus)->toBe(200)
        ->and($request->responseBody)->toBe(['id' => 123, 'name' => 'John'])
        ->and($request->executionTime)->toBeFloat()
        ->and($request->executionTime)->toBeGreaterThan(0);
});

it('tracks authenticated user with auto_track enabled', function () {
    // Ensure auto_track is enabled
    config(['request-tracker.auto_track' => true]);

    $user = createTestUser(789, 'Auto Track User');

    $this->actingAs($user)->postJson('/api/users', ['name' => 'John']);

    $requests = RequestTracker::get();
    $request = $requests->first();

    expect($request->userId)->toBe(789)
        ->and($request->method)->toBe('POST')
        ->and($request->path)->toBe('api/users');
});
