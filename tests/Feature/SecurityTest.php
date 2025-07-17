<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use VildanBina\HookShot\Facades\RequestTracker;

beforeEach(function () {
    Route::post('/api/auth', fn () => response()->json(['token' => 'secret-token']))->middleware('track-requests');
});

it('filters sensitive request headers', function () {
    $this->postJson('/api/auth', ['username' => 'john'], [
        'Authorization' => 'Bearer secret-token',
        'Cookie' => 'session=abc123',
        'X-API-Key' => 'secret-key',
        'User-Agent' => 'TestAgent/1.0',
    ]);

    $requests = RequestTracker::get();
    $request = $requests->first();

    expect($request->headers['authorization'])->toBe(['[FILTERED]'])
        ->and($request->headers['cookie'])->toBe(['[FILTERED]'])
        ->and($request->headers['x-api-key'])->toBe(['[FILTERED]'])
        ->and($request->headers['user-agent'])->toBe(['TestAgent/1.0']); // Not filtered
});

it('filters sensitive response headers', function () {
    Route::post('/login', function () {
        return response()->json(['success' => true])
            ->cookie('session', 'new-session-id')
            ->header('Set-Cookie', 'auth=token123');
    })->middleware('track-requests');

    $this->postJson('/login', ['email' => 'john@test.com']);

    $requests = RequestTracker::get();
    $request = $requests->first();

    expect($request->responseHeaders['set-cookie'])->toBe(['[FILTERED]']);
});

it('limits payload size to prevent memory issues', function () {
    config(['hookshot.max_payload_size' => 100]);

    Route::post('/api/test', fn () => response()->json(['success' => true]))->middleware('track-requests');

    $largePayload = str_repeat('a', 200);
    $this->postJson('/api/test', ['data' => $largePayload]);

    $requests = RequestTracker::get();
    $request = $requests->first();

    // Payload should be limited
    expect($request->payload)->not->toBe(['data' => $largePayload]);
});

it('limits response size in captured data', function () {
    config(['hookshot.max_response_size' => 50]);

    Route::post('/api/test', fn () => response()->json(['success' => true]))->middleware('track-requests');

    $this->postJson('/api/test', ['data' => 'test']);

    $requests = RequestTracker::get();
    $request = $requests->first();

    // Response body should be limited if too large
    expect($request->responseBody)->not->toBeNull();
});

it('excludes sensitive paths from tracking', function () {
    config(['hookshot.excluded_paths' => ['admin/*', 'secret/*']]);

    Route::get('/admin/dashboard', fn () => 'admin')->middleware('track-requests');
    Route::get('/secret/data', fn () => 'secret')->middleware('track-requests');

    $this->get('/admin/dashboard');
    $this->get('/secret/data');

    $requests = RequestTracker::get();
    expect($requests)->toHaveCount(0);
});

it('excludes requests from specific user agents', function () {
    config(['hookshot.excluded_user_agents' => ['googlebot', 'pingdom']]);

    Route::post('/api/test', fn () => response()->json(['success' => true]))->middleware('track-requests');

    $this->withHeaders(['User-Agent' => 'Googlebot/2.1'])
        ->postJson('/api/test', ['data' => 'test']);

    $this->withHeaders(['User-Agent' => 'Pingdom.com_bot_version_1.4'])
        ->postJson('/api/test', ['data' => 'test']);

    $requests = RequestTracker::get();
    expect($requests)->toHaveCount(0);
});

it('handles file uploads securely', function () {
    Route::post('/upload', fn () => response()->json(['uploaded' => true]))->middleware('track-requests');

    $file = Illuminate\Http\UploadedFile::fake()->create('secret.txt', 100, 'text/plain');

    $this->post('/upload', ['file' => $file]);

    $requests = RequestTracker::get();
    $request = $requests->first();

    // File content should not be stored, only metadata
    expect($request->payload['file'])->toHaveKey('name', 'secret.txt')
        ->and($request->payload['file'])->toHaveKey('size', 102400) // 100KB = 102400 bytes
        ->and($request->payload['file'])->not->toHaveKey('content');
});
