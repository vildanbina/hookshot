<?php

declare(strict_types=1);

namespace VildanBina\HookShot\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use VildanBina\HookShot\Data\RequestData;

/**
 * Event fired when a request has been captured and is ready for storage.
 */
class RequestCaptured
{
    use Dispatchable;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public readonly Request $request,
        public readonly RequestData $requestData
    ) {}
}
