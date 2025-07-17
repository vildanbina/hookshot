<?php

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Response;
use VildanBina\HookShot\Support\ResponseCapture;

it('captures response headers', function () {
    $capture = new ResponseCapture([]);
    $response = new Response();
    $response->headers->set('Content-Type', 'application/json');
    $response->headers->set('Set-Cookie', 'session=abc123');

    $headers = $capture->getHeaders($response);

    expect($headers['content-type'])->toBe(['application/json'])
        ->and($headers['set-cookie'])->toBe(['[FILTERED]']); // Sensitive header filtered
});

it('captures JSON response body', function () {
    $capture = new ResponseCapture([]);
    $data = ['id' => 123, 'name' => 'John'];
    $response = new Response(json_encode($data), 200, ['Content-Type' => 'application/json']);

    $body = $capture->getBody($response);

    expect($body)->toBe($data);
});

it('captures text response body', function () {
    $capture = new ResponseCapture([]);
    $response = new Response('Hello World', 200, ['Content-Type' => 'text/plain']);

    $body = $capture->getBody($response);

    expect($body)->toBe('Hello World');
});

it('skips binary response bodies', function () {
    $capture = new ResponseCapture([]);
    $response = new Response('binary-data', 200, ['Content-Type' => 'application/pdf']);

    $body = $capture->getBody($response);

    expect($body)->toBeNull();
});

it('limits response body size', function () {
    $capture = new ResponseCapture(['max_response_size' => 20]);
    $longContent = str_repeat('x', 100);
    $response = new Response($longContent, 200, ['Content-Type' => 'text/plain']);

    $body = $capture->getBody($response);

    expect($body)->toHaveKey('_truncated')
        ->and($body['_truncated'])->toBeTrue()
        ->and($body['_original_size'])->toBe(100);
});

it('handles malformed JSON gracefully', function () {
    $capture = new ResponseCapture([]);
    $response = new Response('{"invalid": json}', 200, ['Content-Type' => 'application/json']);

    $body = $capture->getBody($response);

    expect($body)->toBe('{"invalid": json}'); // Falls back to raw content
});

it('skips response body for redirects', function () {
    $capture = new ResponseCapture([]);
    $response = new Response('', 302, ['Location' => 'https://app.test/dashboard']);

    $body = $capture->getBody($response);

    expect($body)->toBeNull();
});
