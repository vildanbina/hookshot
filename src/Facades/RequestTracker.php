<?php

declare(strict_types=1);

namespace VildanBina\HookShot\Facades;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use VildanBina\HookShot\Contracts\RequestTrackerContract;
use VildanBina\HookShot\Data\RequestData;

/**
 * Facade for the request tracker service.
 *
 * @method static bool store(RequestData $requestData)
 * @method static RequestData|null find(string $id)
 * @method static Collection get(array $filters = [], int $limit = 100)
 * @method static bool delete(string $id)
 * @method static int cleanup()
 */
class RequestTracker extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return RequestTrackerContract::class;
    }
}
