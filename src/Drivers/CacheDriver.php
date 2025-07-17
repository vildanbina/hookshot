<?php

declare(strict_types=1);

namespace VildanBina\HookShot\Drivers;

use Exception;
use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use VildanBina\HookShot\Contracts\StorageDriverContract;
use VildanBina\HookShot\Data\RequestData;

/**
 * Cache storage driver for request tracking data.
 */
class CacheDriver implements StorageDriverContract
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly CacheManager $cache,
        private readonly array $config = []
    ) {}

    /**
     * Store request data in cache.
     */
    public function store(RequestData $requestData): bool
    {
        try {
            $key = $this->getKey($requestData->id);
            $ttl = $this->getTtl();

            $stored = $this->getStore()->put($key, $requestData->toArray(), $ttl);

            // Also add to index for querying
            $this->addToIndex($requestData);

            return $stored;
        } catch (Exception $e) {
            logger()->error('Failed to store request data in cache', [
                'exception' => $e->getMessage(),
                'request_id' => $requestData->id,
            ]);

            return false;
        }
    }

    /**
     * Find request data by ID.
     */
    public function find(string $id): ?RequestData
    {
        try {
            $key = $this->getKey($id);
            $data = $this->getStore()->get($key);

            return $data ? RequestData::fromArray($data) : null;
        } catch (Exception $e) {
            logger()->error('Failed to find request data in cache', [
                'exception' => $e->getMessage(),
                'request_id' => $id,
            ]);

            return null;
        }
    }

    /**
     * Get collection of all cached requests.
     * Users can chain additional collection methods for filtering.
     */
    public function collection(): Collection
    {
        try {
            $indexKey = $this->getIndexKey();
            /** @var array<int, array{id: string, timestamp: string}> $index */
            $index = $this->getStore()->get($indexKey, []);

            return collect($index)
                ->sortByDesc('timestamp')
                ->map(fn ($item) => $this->find($item['id']))
                ->filter();
        } catch (Exception $e) {
            logger()->error('Failed to get request data from cache', [
                'exception' => $e->getMessage(),
            ]);

            return collect();
        }
    }

    /**
     * Get multiple requests with basic ordering and limit.
     */
    public function get(int $limit = 100): Collection
    {
        return $this->collection()->take($limit);
    }

    /**
     * Delete request data by ID.
     */
    public function delete(string $id): bool
    {
        try {
            $key = $this->getKey($id);
            $deleted = $this->getStore()->forget($key);

            $this->removeFromIndex($id);

            return $deleted;
        } catch (Exception $e) {
            logger()->error('Failed to delete request data from cache', [
                'exception' => $e->getMessage(),
                'request_id' => $id,
            ]);

            return false;
        }
    }

    /**
     * Clean up old request data (cache handles TTL automatically).
     */
    public function cleanup(): int
    {
        try {
            $indexKey = $this->getIndexKey();
            $index = $this->getStore()->get($indexKey, []);

            $retentionDays = $this->config['retention_days'] ?? 7;
            $cutoffDate = Carbon::now()->subDays($retentionDays);

            $cleaned = 0;
            $newIndex = [];

            foreach ($index as $item) {
                $timestamp = Carbon::parse($item['timestamp']);

                if ($timestamp->gt($cutoffDate)) {
                    $newIndex[] = $item;
                } else {
                    $this->getStore()->forget($this->getKey($item['id']));
                    $cleaned++;
                }
            }

            $this->getStore()->put($indexKey, $newIndex, $this->getTtl());

            return $cleaned;
        } catch (Exception $e) {
            logger()->error('Failed to cleanup request data from cache', [
                'exception' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Check if the driver is available.
     */
    public function isAvailable(): bool
    {
        try {
            $this->getStore()->put('hookshot_test', 'test', 1);
            $this->getStore()->forget('hookshot_test');

            return true;
        } catch (Exception) {
            return false;
        }
    }

    /**
     * Get cache store instance.
     */
    private function getStore(): Repository|Store
    {
        $store = $this->config['store'] ?? null;

        return $this->cache->store($store);
    }

    /**
     * Generate cache key for individual request.
     */
    private function getKey(string $id): string
    {
        $prefix = $this->config['prefix'] ?? 'hookshot';

        return "{$prefix}:request:{$id}";
    }

    /**
     * Generate cache key for index.
     */
    private function getIndexKey(): string
    {
        $prefix = $this->config['prefix'] ?? 'hookshot';

        return "{$prefix}:index";
    }

    /**
     * Get TTL for cache entries.
     */
    private function getTtl(): int
    {
        $retentionDays = $this->config['retention_days'] ?? 7;

        return $retentionDays * 24 * 60; // Convert to minutes
    }

    /**
     * Add request to index for querying.
     */
    private function addToIndex(RequestData $requestData): void
    {
        $indexKey = $this->getIndexKey();
        $index = $this->getStore()->get($indexKey, []);

        $indexItem = [
            'id' => $requestData->id,
            'method' => $requestData->method,
            'path' => $requestData->path,
            'ip' => $requestData->ip,
            'user_id' => $requestData->userId,
            'timestamp' => $requestData->timestamp->toISOString(),
            'response_status' => $requestData->responseStatus,
        ];

        $index[] = $indexItem;

        // Keep index size manageable
        $maxIndexSize = $this->config['max_index_size'] ?? 10000;
        if (count($index) > $maxIndexSize) {
            $index = array_slice($index, -(int) $maxIndexSize);
        }

        $this->getStore()->put($indexKey, $index, $this->getTtl());
    }

    /**
     * Remove request from index.
     */
    private function removeFromIndex(string $id): void
    {
        $indexKey = $this->getIndexKey();
        $index = $this->getStore()->get($indexKey, []);

        $index = array_filter($index, fn ($item) => $item['id'] !== $id);

        $this->getStore()->put($indexKey, array_values($index), $this->getTtl());
    }
}
