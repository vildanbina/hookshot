<?php

declare(strict_types=1);

use VildanBina\HookShot\Support\RequestFilter;

it('allows tracking when enabled', function () {
    $filter = new RequestFilter(['enabled' => true]);
    $request = mockRequest();

    expect($filter->shouldTrack($request))->toBeTrue();
});

it('blocks tracking when disabled', function () {
    $filter = new RequestFilter(['enabled' => false]);
    $request = mockRequest();

    expect($filter->shouldTrack($request))->toBeFalse();
});

it('excludes requests by path pattern', function () {
    $filter = new RequestFilter([
        'enabled' => true,
        'excluded_paths' => ['health-check', 'api/internal/*'],
    ]);

    $healthRequest = mockRequest(['url' => 'https://app.test/health-check']);
    $internalRequest = mockRequest(['url' => 'https://app.test/api/internal/metrics']);
    $normalRequest = mockRequest(['url' => 'https://app.test/api/users']);

    expect($filter->shouldTrack($healthRequest))->toBeFalse()
        ->and($filter->shouldTrack($internalRequest))->toBeFalse()
        ->and($filter->shouldTrack($normalRequest))->toBeTrue();
});

it('excludes requests by user agent', function () {
    $filter = new RequestFilter([
        'enabled' => true,
        'excluded_user_agents' => ['pingdom', 'uptimerobot'],
    ]);

    $botRequest = mockRequest(['headers' => ['User-Agent' => 'pingdom']]);
    $monitorRequest = mockRequest(['headers' => ['User-Agent' => 'UptimeRobot/2.0']]);
    $normalRequest = mockRequest(['headers' => ['User-Agent' => 'Mozilla/5.0']]);

    expect($filter->shouldTrack($botRequest))->toBeFalse()
        ->and($filter->shouldTrack($monitorRequest))->toBeFalse()
        ->and($filter->shouldTrack($normalRequest))->toBeTrue();
});

it('respects sampling rate', function () {
    // Test with 0% sampling
    $filter = new RequestFilter(['enabled' => true, 'sampling_rate' => 0.0]);
    $request = mockRequest();

    expect($filter->shouldTrack($request))->toBeFalse();

    // Test with 100% sampling
    $filter = new RequestFilter(['enabled' => true, 'sampling_rate' => 1.0]);

    expect($filter->shouldTrack($request))->toBeTrue();
});

it('combines all filters', function () {
    $filter = new RequestFilter([
        'enabled' => true,
        'excluded_paths' => ['admin/*'],
        'excluded_user_agents' => ['bot'],
        'sampling_rate' => 1.0,
    ]);

    // Should be excluded by path
    $adminRequest = mockRequest(['url' => 'https://app.test/admin/users']);
    expect($filter->shouldTrack($adminRequest))->toBeFalse();

    // Should be excluded by user agent
    $botRequest = mockRequest(['headers' => ['User-Agent' => 'bot/1.0']]);
    expect($filter->shouldTrack($botRequest))->toBeFalse();

    // Should pass all filters
    $normalRequest = mockRequest();
    expect($filter->shouldTrack($normalRequest))->toBeTrue();
});
