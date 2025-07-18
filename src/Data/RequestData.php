<?php

declare(strict_types=1);

namespace VildanBina\HookShot\Data;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;
use VildanBina\HookShot\Support\DataExtractor;

/**
 * Immutable data object representing a captured HTTP request.
 */
class RequestData
{
    public function __construct(
        public readonly string $id,
        public readonly string $method,
        public readonly string $url,
        public readonly string $path,
        public readonly array $headers,
        public readonly array $query,
        public readonly mixed $payload,
        public readonly string $ip,
        public readonly string $userAgent,
        public readonly ?int $userId,
        public readonly array $metadata,
        public readonly Carbon $timestamp,
        public readonly float $executionTime = 0.0,
        public readonly int $responseStatus = 0,
        public readonly array $responseHeaders = [],
        public readonly mixed $responseBody = null
    ) {}

    /**
     * Create a new instance from an HTTP request.
     */
    public static function fromRequest(Request $request, ?DataExtractor $extractor = null): self
    {
        if ($extractor === null) {
            throw new InvalidArgumentException('DataExtractor is required for fromRequest method');
        }

        return new self(
            id: Str::uuid()->toString(),
            method: $request->method(),
            url: $request->fullUrl(),
            path: $request->path(),
            headers: $extractor->filterHeaders($request->headers->all()),
            query: $request->query->all(),
            payload: $extractor->extractPayload($request),
            ip: $request->ip(),
            userAgent: $request->userAgent() ?? '',
            userId: $request->user()?->getKey(),
            metadata: $extractor->extractMetadata($request),
            timestamp: now()
        );
    }

    /**
     * Create a new instance from an array of data.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            method: $data['method'],
            url: $data['url'],
            path: $data['path'],
            headers: $data['headers'] ?? [],
            query: $data['query'] ?? [],
            payload: $data['payload'],
            ip: $data['ip'],
            userAgent: $data['user_agent'] ?? '',
            userId: $data['user_id'],
            metadata: $data['metadata'] ?? [],
            timestamp: Carbon::parse($data['timestamp']),
            executionTime: $data['execution_time'] ?? 0.0,
            responseStatus: $data['response_status'] ?? 0,
            responseHeaders: $data['response_headers'] ?? [],
            responseBody: $data['response_body']
        );
    }

    /**
     * Create a new instance with response data added.
     *
     * @param  array<string, mixed>  $headers
     */
    public function withResponse(int $status, array $headers, mixed $body, float $executionTime): self
    {
        return new self(
            $this->id,
            $this->method,
            $this->url,
            $this->path,
            $this->headers,
            $this->query,
            $this->payload,
            $this->ip,
            $this->userAgent,
            $this->userId,
            $this->metadata,
            $this->timestamp,
            $executionTime,
            $status,
            $headers,
            $body
        );
    }

    /**
     * Convert the request data to an associative array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'method' => $this->method,
            'url' => $this->url,
            'path' => $this->path,
            'headers' => $this->headers,
            'query' => $this->query,
            'payload' => $this->payload,
            'ip' => $this->ip,
            'user_agent' => $this->userAgent,
            'user_id' => $this->userId,
            'metadata' => $this->metadata,
            'timestamp' => $this->timestamp->toISOString(),
            'execution_time' => $this->executionTime,
            'response_status' => $this->responseStatus,
            'response_headers' => $this->responseHeaders,
            'response_body' => $this->responseBody,
        ];
    }
}
