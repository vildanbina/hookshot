<?php

declare(strict_types=1);

use VildanBina\HookShot\Data\RequestData;
use VildanBina\HookShot\Events\RequestCaptured;

it('creates event with request and request data', function () {
    $request = mockRequest();
    $data = requestData();
    $requestData = RequestData::fromArray($data);

    $event = new RequestCaptured($request, $requestData);

    expect($event->request)->toBe($request)
        ->and($event->requestData)->toBe($requestData);
});

it('is dispatchable', function () {
    $request = mockRequest();
    $data = requestData();
    $requestData = RequestData::fromArray($data);

    $event = new RequestCaptured($request, $requestData);

    expect(method_exists($event, 'dispatch'))->toBeTrue();
});

it('allows access to request properties', function () {
    $request = mockRequest(['method' => 'POST', 'url' => 'https://app.test/api/users']);
    $data = requestData();
    $requestData = RequestData::fromArray($data);

    $event = new RequestCaptured($request, $requestData);

    expect($event->request->getMethod())->toBe('POST')
        ->and($event->requestData->method)->toBe('POST');
});
