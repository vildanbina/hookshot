<?php

declare(strict_types=1);

namespace VildanBina\HookShot\Contracts;

use Illuminate\Support\Collection;
use VildanBina\HookShot\Data\RequestData;

/**
 * Contract for storage drivers that persist request tracking data.
 */
interface StorageDriverContract
{
    /**
     * Store request data.
     */
    public function store(RequestData $requestData): bool;

    /**
     * Find request data by ID.
     */
    public function find(string $id): ?RequestData;

    /**
     * Get multiple requests with basic ordering and limit.
     */
    public function get(int $limit = 100): Collection;

    /**
     * Delete request data by ID.
     */
    public function delete(string $id): bool;

    /**
     * Clean up old request data.
     */
    public function cleanup(): int;

    /**
     * Check if the driver is available and functional.
     */
    public function isAvailable(): bool;
}
