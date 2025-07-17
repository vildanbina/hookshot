<?php

declare(strict_types=1);

namespace VildanBina\HookShot\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use VildanBina\HookShot\Data\RequestData;

class RequestCaptured
{
    use Dispatchable;

    public function __construct(
        public readonly Request $request,
        public readonly RequestData $requestData
    ) {}
}
