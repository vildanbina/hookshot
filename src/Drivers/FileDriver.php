<?php

declare(strict_types=1);

namespace VildanBina\HookShot\Drivers;

use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use VildanBina\HookShot\Contracts\StorageDriverContract;
use VildanBina\HookShot\Data\RequestData;

/**
 * File system storage driver for request tracking data.
 */
class FileDriver implements StorageDriverContract
{
    private readonly string $storagePath;

    private readonly string $format;

    private readonly int $retentionDays;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(private readonly array $config = [])
    {
        $this->storagePath = $this->config['path'] ?? storage_path('app/hookshot');
        $this->format = $this->config['format'] ?? 'json'; // json or raw
        $this->retentionDays = $this->config['retention_days'] ?? 30;

        try {
            $this->ensureDirectoryExists();
        } catch (Exception $e) {
            logger()->error('FileDriver failed to create storage directory', [
                'exception' => $e->getMessage(),
                'path' => $this->storagePath,
            ]);
        }
    }

    public function store(RequestData $requestData): bool
    {
        try {
            $filename = $this->getFilename($requestData);
            $content = $this->formatContent($requestData);

            return File::put($filename, $content) !== false;
        } catch (Exception $e) {
            logger()->error('FileDriver storage failed', [
                'exception' => $e->getMessage(),
                'request_id' => $requestData->id,
            ]);

            return false;
        }
    }

    public function find(string $id): ?RequestData
    {
        $pattern = $this->storagePath.'/*/'.$id.'.*';
        $files = glob($pattern);

        if ($files === false || empty($files)) {
            return null;
        }

        try {
            $content = File::get($files[0]);

            return $this->parseContent($content);
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Get collection of all file-based requests.
     * Users can chain additional collection methods for filtering.
     */
    public function collection(): Collection
    {
        $results = collect();
        $directories = $this->getDirectories();

        foreach ($directories as $directory) {
            $dirResults = $this->searchDirectory($directory, PHP_INT_MAX);
            $results = $results->merge($dirResults);
        }

        return $results;
    }

    /**
     * Get multiple requests with basic ordering and limit.
     */
    public function get(int $limit = 100): Collection
    {
        return $this->collection()->take($limit);
    }

    public function delete(string $id): bool
    {
        $pattern = $this->storagePath.'/*/'.$id.'.*';
        $files = glob($pattern);

        if ($files === false) {
            return false;
        }

        $deleted = false;
        foreach ($files as $file) {
            if (File::delete($file)) {
                $deleted = true;
            }
        }

        return $deleted;
    }

    public function cleanup(): int
    {
        $cutoffDate = Carbon::now()->subDays($this->retentionDays);
        $deletedCount = 0;

        $directories = File::directories($this->storagePath);

        foreach ($directories as $directory) {
            $dirDate = $this->extractDateFromDirectory(basename($directory));

            if ($dirDate && $dirDate->lt($cutoffDate)) {
                // Count files before deleting the directory
                $files = File::files($directory);
                $fileCount = count($files);

                if (File::deleteDirectory($directory)) {
                    $deletedCount += $fileCount;
                }
            }
        }

        return $deletedCount;
    }

    public function isAvailable(): bool
    {
        return File::isWritable($this->storagePath);
    }

    private function ensureDirectoryExists(): void
    {
        if (! File::exists($this->storagePath)) {
            File::makeDirectory($this->storagePath, 0755, true);
        }
    }

    private function getFilename(RequestData $requestData): string
    {
        $dateDir = $this->storagePath.'/'.$requestData->timestamp->format('Y-m-d');

        if (! File::exists($dateDir)) {
            File::makeDirectory($dateDir, 0755, true);
        }

        $extension = $this->format === 'json' ? 'json' : 'txt';

        return $dateDir.'/'.$requestData->id.'.'.$extension;
    }

    private function formatContent(RequestData $requestData): string
    {
        return match ($this->format) {
            'raw' => $this->formatRawContent($requestData),
            default => json_encode($requestData->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}',
        };
    }

    private function formatRawContent(RequestData $requestData): string
    {
        $lines = [];
        $lines[] = "Request ID: {$requestData->id}";
        $lines[] = "Timestamp: {$requestData->timestamp->toISOString()}";
        $lines[] = "Method: {$requestData->method}";
        $lines[] = "URL: {$requestData->url}";
        $lines[] = "IP: {$requestData->ip}";
        $lines[] = "User Agent: {$requestData->userAgent}";
        $lines[] = 'User ID: '.($requestData->userId ?? 'N/A');
        $lines[] = "Execution Time: {$requestData->executionTime}s";
        $lines[] = "Response Status: {$requestData->responseStatus}";
        $lines[] = '';

        $lines[] = 'Headers:';
        foreach ($requestData->headers as $name => $values) {
            $valueStr = is_array($values) ? implode(', ', $values) : $values;
            $lines[] = "  {$name}: {$valueStr}";
        }
        $lines[] = '';

        if (! empty($requestData->query)) {
            $lines[] = 'Query Parameters:';
            foreach ($requestData->query as $key => $value) {
                $lines[] = "  {$key}: ".json_encode($value);
            }
            $lines[] = '';
        }

        if ($requestData->payload !== null) {
            $lines[] = 'Payload:';
            $lines[] = json_encode($requestData->payload, JSON_PRETTY_PRINT);
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    private function parseContent(string $content): ?RequestData
    {
        try {
            if ($this->format === 'json') {
                $data = json_decode($content, true);

                return $data ? RequestData::fromArray($data) : null;
            }

            // For raw format, parsing would be more complex
            // In practice, you might store metadata separately for querying
            return null;
        } catch (Exception) {
            return null;
        }
    }

    private function getDirectories(): array
    {
        $directories = File::directories($this->storagePath);

        // Sort by date (newest first)
        usort($directories, function ($a, $b) {
            return basename($b) <=> basename($a);
        });

        return $directories;
    }

    private function searchDirectory(string $directory, int $limit): Collection
    {
        $results = collect();
        $files = File::files($directory);

        // Sort files by modification time (newest first)
        usort($files, fn ($a, $b) => $b->getMTime() <=> $a->getMTime());

        foreach ($files as $file) {
            if ($results->count() >= $limit) {
                break;
            }

            try {
                $content = File::get($file->getPathname());
                if ($requestData = $this->parseContent($content)) {
                    $results->push($requestData);
                }
            } catch (Exception) {
                continue;
            }
        }

        return $results;
    }

    private function extractDateFromDirectory(string $dirname): ?Carbon
    {
        try {
            return Carbon::createFromFormat('Y-m-d', $dirname);
        } catch (Exception) {
            return null;
        }
    }
}
