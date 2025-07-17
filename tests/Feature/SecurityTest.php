<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use VildanBina\HookShot\Facades\RequestTracker;

beforeEach(function () {
    Route::post('/api/auth', fn () => response()->json(['token' => 'secret-token']));
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
    });

    $this->postJson('/login', ['email' => 'john@test.com']);

    $requests = RequestTracker::get();
    $request = $requests->first();

    expect($request->responseHeaders['set-cookie'])->toBe(['[FILTERED]']);
});

it('limits payload size for security', function () {
    config(['request-tracker.max_payload_size' => 100]);

    $largePayload = ['data' => str_repeat('x', 500)];

    $this->postJson('/api/auth', $largePayload);

    $requests = RequestTracker::get();
    $request = $requests->first();

    expect($request->payload)->toHaveKey('_truncated')
        ->and($request->payload['_truncated'])->toBeTrue()
        ->and($request->payload['_original_size'])->toBeGreaterThan(100);
});

it('limits response size for security', function () {
    config(['request-tracker.max_response_size' => 50]);

    Route::post('/large-response', fn () => response()->json([
        'data' => str_repeat('x', 200),
    ]));

    $this->postJson('/large-response');

    $requests = RequestTracker::get();
    $request = $requests->first();

    expect($request->responseBody)->toHaveKey('_truncated')
        ->and($request->responseBody['_truncated'])->toBeTrue();
});

it('excludes sensitive paths from tracking', function () {
    config(['request-tracker.excluded_paths' => ['admin/*', 'secret/*']]);

    Route::get('/admin/users', fn () => 'admin page');
    Route::get('/secret/keys', fn () => 'secret page');
    Route::get('/public/info', fn () => 'public page');

    $this->get('/admin/users');
    $this->get('/secret/keys');
    $this->get('/public/info');

    $requests = RequestTracker::get();

    expect($requests)->toHaveCount(1)
        ->and($requests->first()->path)->toBe('public/info');
});

it('excludes bot user agents from tracking', function () {
    config(['request-tracker.excluded_user_agents' => ['googlebot', 'pingdom']]);

    Route::get('/api/data', fn () => response()->json(['data' => 'value']));

    $this->withHeader('User-Agent', 'Googlebot/2.1')->get('/api/data');
    $this->withHeader('User-Agent', 'Pingdom.com_bot')->get('/api/data');
    $this->withHeader('User-Agent', 'Mozilla/5.0')->get('/api/data');

    $requests = RequestTracker::get();

    expect($requests)->toHaveCount(1)
        ->and($requests->first()->userAgent)->toBe('Mozilla/5.0');
});

it('handles file uploads securely', function () {
    Route::post('/upload', fn () => response()->json(['uploaded' => true]));

    $file = Illuminate\Http\UploadedFile::fake()->create('secret.txt', 100, 'text/plain');

    $this->post('/upload', ['file' => $file]);

    $requests = RequestTracker::get();
    $request = $requests->first();

    // File content should not be stored, only metadata
    expect($request->payload['file'])->toHaveKey('name', 'secret.txt')
        ->and($request->payload['file'])->toHaveKey('size', 102400) // 100KB = 102400 bytes
        ->and($request->payload['file'])->not->toHaveKey('content');
});
