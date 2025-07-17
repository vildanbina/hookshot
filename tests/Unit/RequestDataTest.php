<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use VildanBina\HookShot\Data\RequestData;
use VildanBina\HookShot\Support\DataExtractor;

it('creates request data from array', function () {
    $data = requestData();
    $requestData = RequestData::fromArray($data);

    expect($requestData->id)->toBe($data['id'])
        ->and($requestData->method)->toBe('POST')
        ->and($requestData->url)->toBe('https://app.test/api/users')
        ->and($requestData->payload)->toBe(['name' => 'John', 'email' => 'john@example.com'])
        ->and($requestData->timestamp)->toBeInstanceOf(Carbon::class);
});

it('creates request data from HTTP request', function () {
    $request = mockRequest();
    $extractor = new DataExtractor(config('hookshot'));

    $requestData = RequestData::fromRequest($request, $extractor);

    expect($requestData->method)->toBe('POST')
        ->and($requestData->url)->toContain('api/users')
        ->and($requestData->path)->toBe('api/users')
        ->and($requestData->headers)->toBeArray()
        ->and($requestData->timestamp)->toBeInstanceOf(Carbon::class);
});

it('requires data extractor when creating from request', function () {
    $request = mockRequest();

    RequestData::fromRequest($request, null);
})->throws(InvalidArgumentException::class, 'DataExtractor is required');

it('converts to array format', function () {
    $data = requestData();
    $requestData = RequestData::fromArray($data);
    $array = $requestData->toArray();

    expect($array)->toHaveKey('id')
        ->and($array)->toHaveKey('method')
        ->and($array)->toHaveKey('payload')
        ->and($array['timestamp'])->toBeString();
});

it('updates response data', function () {
    $data = requestData(['response_status' => 0]);
    $requestData = RequestData::fromArray($data);

    $updated = $requestData->withResponse(
        status: 201,
        headers: ['content-type' => ['application/json']],
        body: ['success' => true],
        executionTime: 0.5
    );

    expect($updated->responseStatus)->toBe(201)
        ->and($updated->responseHeaders)->toBe(['content-type' => ['application/json']])
        ->and($updated->responseBody)->toBe(['success' => true])
        ->and($updated->executionTime)->toBe(0.5)
        ->and($updated->id)->toBe($requestData->id); // Immutable
});
