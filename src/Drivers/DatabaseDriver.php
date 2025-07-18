<?php

declare(strict_types=1);

namespace VildanBina\HookShot\Drivers;

use Exception;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use VildanBina\HookShot\Contracts\StorageDriverContract;
use VildanBina\HookShot\Data\RequestData;
use VildanBina\HookShot\Support\DataExtractor;

/**
 * Database storage driver for request tracking data.
 */
class DatabaseDriver implements StorageDriverContract
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly DataExtractor $dataExtractor,
        private readonly array $config = []
    ) {}

    /**
     * Store request data in the database.
     */
    public function store(RequestData $requestData): bool
    {
        try {
            $data = $requestData->toArray();

            $data['headers'] = $this->dataExtractor->encodeJson($data['headers']);
            $data['query'] = $this->dataExtractor->encodeJson($data['query']);
            $data['payload'] = $this->dataExtractor->encodeJson($data['payload']);
            $data['metadata'] = $this->dataExtractor->encodeJson($data['metadata']);
            $data['response_headers'] = $this->dataExtractor->encodeJson($data['response_headers']);
            $data['response_body'] = $this->dataExtractor->encodeJson($data['response_body']);
            $data['timestamp'] = $requestData->timestamp;

            $this->getConnection()->table($this->getTableName())->insert($data);

            return true;
        } catch (Exception $e) {
            logger()->error('Failed to store request data', [
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
        $record = $this->getConnection()
            ->table($this->getTableName())
            ->where('id', $id)
            ->first();

        return $record ? $this->mapToRequestData((array) $record) : null;
    }

    /**
     * Get query builder for requests table.
     * Users can chain additional conditions, ordering, etc.
     */
    public function query(): Builder
    {
        return $this->getConnection()->table($this->getTableName());
    }

    /**
     * Get multiple requests with basic ordering and limit.
     */
    public function get(int $limit = 100): Collection
    {
        return $this->query()
            ->orderBy('timestamp', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn ($record) => $this->mapToRequestData((array) $record));
    }

    /**
     * Delete request data by ID.
     */
    public function delete(string $id): bool
    {
        try {
            $deleted = $this->getConnection()
                ->table($this->getTableName())
                ->where('id', $id)
                ->delete();

            return $deleted > 0;
        } catch (Exception $e) {
            logger()->error('Failed to delete request data', [
                'exception' => $e->getMessage(),
                'request_id' => $id,
            ]);

            return false;
        }
    }

    /**
     * Clean up old request data based on retention policy.
     */
    public function cleanup(): int
    {
        $retentionDays = $this->config['retention_days'] ?? 30;
        $cutoffDate = Carbon::now()->subDays($retentionDays);

        try {
            return $this->getConnection()
                ->table($this->getTableName())
                ->where('timestamp', '<', $cutoffDate)
                ->delete();
        } catch (Exception $e) {
            logger()->error('Failed to cleanup request data', [
                'exception' => $e->getMessage(),
                'cutoff_date' => $cutoffDate,
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
            $this->getConnection()->getPdo();

            return true;
        } catch (Exception) {
            return false;
        }
    }

    /**
     * Get the database connection.
     */
    private function getConnection(): Connection
    {
        $connection = $this->config['connection'] ?? null;

        return $this->database->connection($connection);
    }

    /**
     * Get the table name for storage.
     */
    private function getTableName(): string
    {
        return $this->config['table'];
    }

    /**
     * Map database record to RequestData object.
     *
     * @param  array<string, mixed>  $record  Database record as array
     */
    private function mapToRequestData(array $record): RequestData
    {
        return RequestData::fromArray([
            'id' => $record['id'],
            'method' => $record['method'],
            'url' => $record['url'],
            'path' => $record['path'],
            'headers' => $this->dataExtractor->decodeJson($record['headers'], []),
            'query' => $this->dataExtractor->decodeJson($record['query'], []),
            'payload' => $this->dataExtractor->decodeJson($record['payload'], null),
            'ip' => $record['ip'],
            'user_agent' => $record['user_agent'],
            'user_id' => $record['user_id'],
            'metadata' => $this->dataExtractor->decodeJson($record['metadata'], []),
            'timestamp' => $record['timestamp'],
            'execution_time' => (float) $record['execution_time'],
            'response_status' => $record['response_status'],
            'response_headers' => $this->dataExtractor->decodeJson($record['response_headers'], null),
            'response_body' => $this->dataExtractor->decodeJson($record['response_body'], null),
        ]);
    }
}
