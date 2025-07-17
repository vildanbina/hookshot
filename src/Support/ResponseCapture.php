<?php

declare(strict_types=1);

namespace VildanBina\HookShot\Support;

use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Captures and processes HTTP response data for tracking.
 */
class ResponseCapture
{
    private const EXCLUDED_CONTENT_TYPES = [
        'image/',
        'video/',
        'audio/',
        'application/pdf',
        'application/zip',
        'application/octet-stream',
    ];

    private readonly DataExtractor $dataExtractor;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(private readonly array $config)
    {
        $this->dataExtractor = new DataExtractor($config);
    }

    /**
     * Get filtered response headers.
     *
     * @return array<string, array<string>>
     */
    public function getHeaders(SymfonyResponse $response): array
    {
        return $this->dataExtractor->filterHeaders($response->headers->all());
    }

    /**
     * Extract response body content if appropriate.
     */
    public function getBody(SymfonyResponse $response): mixed
    {
        if (! $this->isAllowedContentType($response) || ! $this->isImportantStatus($response)) {
            return null;
        }

        $content = $response->getContent();

        if ($this->isJsonResponse($response)) {
            $decoded = json_decode($content ?: '', true);
            $data = $decoded !== null ? $decoded : $content;

            return $this->limitContentSize($data, true);
        }

        return $this->limitContentSize($content);
    }

    /**
     * Check if content type is allowed for capture.
     */
    private function isAllowedContentType(SymfonyResponse $response): bool
    {
        $contentType = $response->headers->get('content-type', '');

        foreach (self::EXCLUDED_CONTENT_TYPES as $type) {
            if (str_starts_with($contentType, $type)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if status code is worth capturing.
     */
    private function isImportantStatus(SymfonyResponse $response): bool
    {
        $importantStatuses = [200, 201, 400, 401, 403, 404, 422, 500];

        return in_array($response->getStatusCode(), $importantStatuses);
    }

    /**
     * Check if response is JSON content.
     */
    private function isJsonResponse(SymfonyResponse $response): bool
    {
        $contentType = $response->headers->get('content-type', '');

        return str_contains($contentType, 'application/json');
    }

    /**
     * Limit content size to prevent memory issues.
     */
    private function limitContentSize(mixed $content, bool $isJson = false): mixed
    {
        $maxSize = $this->config['max_response_size'] ?? 10240;

        if ($isJson) {
            $serialized = json_encode($content) ?: '{}';
            if (mb_strlen($serialized) <= $maxSize) {
                return $content;
            }

            return [
                '_truncated' => true,
                '_original_size' => mb_strlen($serialized),
                '_data' => json_decode(mb_substr($serialized, 0, $maxSize - 100), true) ?? mb_substr($serialized, 0, $maxSize - 100),
            ];
        }

        $content = (string) $content;
        if (mb_strlen($content) <= $maxSize) {
            return $content;
        }

        return [
            '_truncated' => true,
            '_original_size' => mb_strlen($content),
            '_data' => mb_substr($content, 0, $maxSize - 100),
        ];
    }
}
