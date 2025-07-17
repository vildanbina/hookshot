<?php

declare(strict_types=1);

namespace VildanBina\HookShot\Contracts;

use Illuminate\Support\Collection;
use VildanBina\HookShot\Data\RequestData;

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
     * Get multiple requests with optional filters.
     */
    public function get(array $filters = [], int $limit = 100): Collection;

    /**
     * Delete request data by ID.
     */
    public function delete(string $id): bool;

    /**
     * Clean up old request data.
     */
    public function cleanup(): int;

    /**
     * Check if the driver is available/configured.
     */
    public function isAvailable(): bool;
}
