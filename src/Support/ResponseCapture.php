<?php

declare(strict_types=1);

namespace VildanBina\HookShot\Support;

use Illuminate\Contracts\Config\Repository as Config;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Captures and processes HTTP response data for tracking.
 */
class ResponseCapture
{
    public function __construct(
        private readonly Config $config,
        private readonly DataExtractor $dataExtractor
    ) {}

    /**
     * Get filtered response headers.
     *
     * @return array<string, array<string>>
     */
    public function getHeaders(SymfonyResponse $response): array
    {
        if (! $this->config->get('hookshot.capture_response_headers', true)) {
            return [];
        }

        return $this->dataExtractor->filterHeaders($response->headers->all());
    }

    /**
     * Extract response body content if appropriate.
     */
    public function getBody(SymfonyResponse $response): mixed
    {
        if (! $this->config->get('hookshot.capture_response_body', true)) {
            return null;
        }

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
        $excludedTypes = $this->config->get('hookshot.excluded_content_types') ?? [];

        foreach ($excludedTypes as $type) {
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
        $importantStatuses = $this->config->get('hookshot.important_status_codes') ?? [];

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
        $maxSize = $this->config->get('hookshot.max_response_size') ?? 10240;

        if ($isJson) {
            $serialized = json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
            $byteSize = mb_strlen($serialized);

            if ($byteSize <= $maxSize) {
                return $content;
            }

            $truncated = mb_substr($serialized, 0, $maxSize - 100);
            $decoded = json_decode($truncated, true);

            return [
                '_truncated' => true,
                '_original_size' => $byteSize,
                '_data' => $decoded ?? $truncated,
            ];
        }

        $content = (string) $content;
        $byteSize = mb_strlen($content);

        if ($byteSize <= $maxSize) {
            return $content;
        }

        return [
            '_truncated' => true,
            '_original_size' => $byteSize,
            '_data' => mb_substr($content, 0, $maxSize - 100),
        ];
    }
}
