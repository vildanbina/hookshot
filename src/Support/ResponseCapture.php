<?php

declare(strict_types=1);

namespace VildanBina\HookShot\Support;

use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

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

    public function __construct(private readonly array $config)
    {
        $this->dataExtractor = new DataExtractor($config);
    }

    public function getHeaders(SymfonyResponse $response): array
    {
        return $this->dataExtractor->filterHeaders($response->headers->all());
    }

    public function getBody(SymfonyResponse $response): mixed
    {
        if (! $this->isAllowedContentType($response) || ! $this->isImportantStatus($response)) {
            return null;
        }

        $content = $response->getContent();

        if ($this->isJsonResponse($response)) {
            $decoded = json_decode($content, true);
            $data = $decoded !== null ? $decoded : $content;

            return $this->limitContentSize($data, true);
        }

        return $this->limitContentSize($content);
    }

    private function shouldCaptureBody(SymfonyResponse $response): bool
    {
        return $this->isAllowedContentType($response)
            && $this->isAllowedSize($response)
            && $this->isImportantStatus($response);
    }

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

    private function isAllowedSize(SymfonyResponse $response): bool
    {
        $contentLength = $response->headers->get('content-length');
        $maxSize = $this->config['max_response_size'] ?? 10240;

        return ! $contentLength || (int) $contentLength <= $maxSize;
    }

    private function isImportantStatus(SymfonyResponse $response): bool
    {
        $importantStatuses = [200, 201, 400, 401, 403, 404, 422, 500];

        return in_array($response->getStatusCode(), $importantStatuses);
    }

    private function isJsonResponse(SymfonyResponse $response): bool
    {
        $contentType = $response->headers->get('content-type', '');

        return str_contains($contentType, 'application/json');
    }

    private function limitContentSize(mixed $content, bool $isJson = false): mixed
    {
        $maxSize = $this->config['max_response_size'] ?? 10240;

        if ($isJson) {
            $serialized = json_encode($content);
            if (mb_strlen($serialized) <= $maxSize) {
                return $content;
            }

            return [
                '_truncated' => true,
                '_original_size' => mb_strlen($serialized),
                '_data' => json_decode(mb_substr($serialized, 0, $maxSize - 100), true) ?? mb_substr($serialized, 0, $maxSize - 100),
            ];
        }

        // String content
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
