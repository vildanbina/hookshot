<?php

declare(strict_types=1);

namespace VildanBina\HookShot\Drivers;

use Exception;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use VildanBina\HookShot\Contracts\StorageDriverContract;
use VildanBina\HookShot\Data\RequestData;

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
        private readonly array $config = []
    ) {}

    /**
     * Store request data in the database.
     */
    public function store(RequestData $requestData): bool
    {
        try {
            $data = $requestData->toArray();

            $data['headers'] = json_encode($data['headers']);
            $data['query'] = json_encode($data['query']);
            $data['payload'] = json_encode($data['payload']);
            $data['metadata'] = json_encode($data['metadata']);
            $data['response_headers'] = json_encode($data['response_headers']);
            $data['response_body'] = json_encode($data['response_body']);
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

        return $record ? $this->mapToRequestData($record) : null;
    }

    /**
     * Get multiple requests with basic ordering and limit.
     *
     * @param  array<string, mixed>  $filters
     */
    public function get(array $filters = [], int $limit = 100): Collection
    {
        $records = $this->getConnection()
            ->table($this->getTableName())
            ->orderBy('timestamp', 'desc')
            ->limit($limit)
            ->get();

        return $records->map(fn ($record) => $this->mapToRequestData($record));
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
    private function getConnection()
    {
        $connection = $this->config['connection'] ?? null;

        return $this->database->connection($connection);
    }

    /**
     * Get the table name for storage.
     */
    private function getTableName(): string
    {
        return $this->config['table'] ?? 'request_tracker_logs';
    }

    /**
     * Map database record to RequestData object.
     */
    private function mapToRequestData(object $record): RequestData
    {
        return RequestData::fromArray([
            'id' => $record->id,
            'method' => $record->method,
            'url' => $record->url,
            'path' => $record->path,
            'headers' => json_decode($record->headers, true) ?? [],
            'query' => json_decode($record->query, true) ?? [],
            'payload' => json_decode($record->payload, true),
            'ip' => $record->ip,
            'user_agent' => $record->user_agent,
            'user_id' => $record->user_id,
            'metadata' => json_decode($record->metadata, true) ?? [],
            'timestamp' => $record->timestamp,
            'execution_time' => (float) $record->execution_time,
            'response_status' => $record->response_status,
            'response_headers' => json_decode($record->response_headers, true) ?? [],
            'response_body' => json_decode($record->response_body, true),
        ]);
    }
}
