<?php

declare(strict_types=1);

namespace VildanBina\HookShot;

use Illuminate\Support\Collection;
use Illuminate\Support\Manager;
use InvalidArgumentException;
use VildanBina\HookShot\Contracts\RequestTrackerContract;
use VildanBina\HookShot\Contracts\StorageDriverContract;
use VildanBina\HookShot\Data\RequestData;
use VildanBina\HookShot\Drivers\CacheDriver;
use VildanBina\HookShot\Drivers\DatabaseDriver;
use VildanBina\HookShot\Drivers\FileDriver;

class RequestTrackerManager extends Manager implements RequestTrackerContract
{
    /**
     * Get the default driver name.
     */
    public function getDefaultDriver(): string
    {
        return $this->config->get('request-tracker.default', 'database');
    }

    /**
     * Store request data using the configured driver.
     */
    public function store(RequestData $requestData): bool
    {
        return $this->driver()->store($requestData);
    }

    /**
     * Retrieve request data by ID.
     */
    public function find(string $id): ?RequestData
    {
        return $this->driver()->find($id);
    }

    /**
     * Retrieve multiple requests with optional filtering.
     */
    public function get(array $filters = [], int $limit = 100): Collection
    {
        return $this->driver()->get($filters, $limit);
    }

    /**
     * Delete request data by ID.
     */
    public function delete(string $id): bool
    {
        return $this->driver()->delete($id);
    }

    /**
     * Delete old request data based on retention policy.
     */
    public function cleanup(): int
    {
        return $this->driver()->cleanup();
    }

    /**
     * Get the current driver instance.
     */
    public function driver($driver = null): StorageDriverContract
    {
        return parent::driver($driver);
    }

    /**
     * Create the database driver.
     */
    protected function createDatabaseDriver(): StorageDriverContract
    {
        return new DatabaseDriver(
            $this->container['db'],
            $this->config->get('request-tracker.drivers.database', [])
        );
    }

    /**
     * Create the cache driver.
     */
    protected function createCacheDriver(): StorageDriverContract
    {
        return new CacheDriver(
            $this->container['cache'],
            $this->config->get('request-tracker.drivers.cache', [])
        );
    }

    /**
     * Create the file driver.
     */
    protected function createFileDriver(): StorageDriverContract
    {
        return new FileDriver(
            $this->config->get('request-tracker.drivers.file', [])
        );
    }

    /**
     * Create a custom driver instance.
     */
    protected function createCustomDriver(array $config): StorageDriverContract
    {
        $driver = $config['via'] ?? null;

        if (! $driver) {
            throw new InvalidArgumentException('Custom driver must specify a "via" key.');
        }

        if (! class_exists($driver)) {
            throw new InvalidArgumentException("Custom driver class [{$driver}] not found.");
        }

        $instance = new $driver($config);

        if (! $instance instanceof StorageDriverContract) {
            throw new InvalidArgumentException("Custom driver [{$driver}] must implement StorageDriverContract.");
        }

        return $instance;
    }
}
