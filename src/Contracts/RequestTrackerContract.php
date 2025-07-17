<?php

declare(strict_types=1);

namespace VildanBina\HookShot\Contracts;

use Illuminate\Support\Collection;
use VildanBina\HookShot\Data\RequestData;

/**
 * Contract for request tracking services.
 */
interface RequestTrackerContract
{
    /**
     * Store request data using the configured driver.
     */
    public function store(RequestData $requestData): bool;

    /**
     * Retrieve request data by ID.
     */
    public function find(string $id): ?RequestData;

    /**
     * Retrieve multiple requests with basic ordering and limit.
     */
    public function get(int $limit = 100): Collection;

    /**
     * Delete request data by ID.
     */
    public function delete(string $id): bool;

    /**
     * Delete old request data based on retention policy.
     */
    public function cleanup(): int;

    /**
     * Get a driver instance.
     */
    public function driver(?string $driver = null): StorageDriverContract;
}
