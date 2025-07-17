<?php

declare(strict_types=1);

use VildanBina\HookShot\Support\DataExtractor;

it('filters sensitive headers', function () {
    $extractor = new DataExtractor([]);

    $headers = [
        'accept' => ['application/json'],
        'authorization' => ['Bearer secret-token'],
        'cookie' => ['session=abc123'],
        'x-api-key' => ['secret-key'],
        'user-agent' => ['TestAgent/1.0'],
    ];

    $filtered = $extractor->filterHeaders($headers);

    expect($filtered['accept'])->toBe(['application/json'])
        ->and($filtered['authorization'])->toBe(['[FILTERED]'])
        ->and($filtered['cookie'])->toBe(['[FILTERED]'])
        ->and($filtered['x-api-key'])->toBe(['[FILTERED]'])
        ->and($filtered['user-agent'])->toBe(['TestAgent/1.0']);
});

it('extracts JSON payload', function () {
    $extractor = new DataExtractor([]);
    $request = mockRequest([
        'content' => json_encode(['name' => 'John', 'email' => 'john@test.com']),
    ]);
    $request->headers->set('Content-Type', 'application/json');

    $payload = $extractor->extractPayload($request);

    expect($payload)->toBe(['name' => 'John', 'email' => 'john@test.com']);
});

it('extracts form payload', function () {
    $extractor = new DataExtractor([]);
    $request = Illuminate\Http\Request::create(
        'https://app.test/form',
        'POST',
        ['name' => 'John', 'email' => 'john@test.com']
    );

    $payload = $extractor->extractPayload($request);

    expect($payload)->toBe(['name' => 'John', 'email' => 'john@test.com']);
});

it('limits payload size', function () {
    $extractor = new DataExtractor(['max_payload_size' => 50]);
    $request = mockRequest([
        'content' => json_encode(['data' => str_repeat('x', 1000)]),
    ]);
    $request->headers->set('Content-Type', 'application/json');

    $payload = $extractor->extractPayload($request);

    expect($payload)->toHaveKey('_truncated')
        ->and($payload['_original_size'])->toBeInt()
        ->and($payload['_truncated'])->toBeTrue();
});

it('extracts file upload metadata', function () {
    $extractor = new DataExtractor([]);
    $file = Illuminate\Http\UploadedFile::fake()->create('test.pdf', 100);

    $request = Illuminate\Http\Request::create('https://app.test/upload', 'POST');
    $request->files->set('document', $file);

    $payload = $extractor->extractPayload($request);

    expect($payload)->toHaveKey('document')
        ->and($payload['document'])->toHaveKey('name', 'test.pdf')
        ->and($payload['document'])->toHaveKey('size', 102400) // 100KB = 102400 bytes
        ->and($payload['document'])->toHaveKey('mime_type');
});

it('extracts request metadata', function () {
    $extractor = new DataExtractor([]);
    $request = mockRequest();
    $request->setRouteResolver(fn () => new Illuminate\Routing\Route(['POST'], 'api/users', []));

    $metadata = $extractor->extractMetadata($request);

    expect($metadata)->toHaveKey('route_name')
        ->and($metadata)->toHaveKey('route_action')
        ->and($metadata)->toHaveKey('content_type')
        ->and($metadata)->toHaveKey('referer');
});
