<?php

declare(strict_types=1);

namespace VildanBina\HookShot\Support;

use Illuminate\Http\Request;

/**
 * Extracts and processes data from HTTP requests for tracking.
 */
class DataExtractor
{
    private const SENSITIVE_HEADERS = ['authorization', 'cookie', 'set-cookie', 'x-api-key', 'x-auth-token'];

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(private readonly array $config) {}

    /**
     * Filter sensitive headers from request headers.
     *
     * @param  array<string, array<string>>  $headers
     * @return array<string, array<string>>
     */
    public function filterHeaders(array $headers): array
    {
        $filtered = [];

        foreach ($headers as $key => $value) {
            if (in_array(mb_strtolower($key), self::SENSITIVE_HEADERS)) {
                $filtered[$key] = ['[FILTERED]'];
            } else {
                $filtered[$key] = $value;
            }
        }

        return $filtered;
    }

    /**
     * Extract and process request payload data.
     */
    public function extractPayload(Request $request): mixed
    {
        if ($request->isJson()) {
            $payload = $request->json()->all();
        } elseif (count($request->allFiles()) > 0) {
            $payload = $this->extractFileData($request);
        } else {
            $payload = $request->all();
        }

        return $this->limitPayloadSize($payload);
    }

    /**
     * Extract request metadata and route information.
     *
     * @return array<string, mixed>
     */
    public function extractMetadata(Request $request): array
    {
        return [
            'route_name' => $request->route()?->getName(),
            'route_action' => $request->route()?->getActionName(),
            'session_id' => $request->hasSession() ? $request->session()->getId() : null,
            'referer' => $request->header('referer'),
            'content_type' => $request->header('content-type'),
        ];
    }

    /**
     * Extract file upload information instead of file contents.
     *
     * @return array<string, mixed>
     */
    private function extractFileData(Request $request): array
    {
        $data = $request->except(array_keys($request->allFiles()));

        foreach ($request->allFiles() as $key => $file) {
            if (is_array($file)) {
                $data[$key] = array_map(fn ($f) => [
                    'name' => $f->getClientOriginalName(),
                    'size' => $f->getSize(),
                    'mime_type' => $f->getMimeType(),
                ], $file);
            } else {
                $data[$key] = [
                    'name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                ];
            }
        }

        return $data;
    }

    /**
     * Limit payload size to prevent memory issues.
     */
    private function limitPayloadSize(mixed $payload): mixed
    {
        $maxSize = $this->config['max_payload_size'] ?? 65536;
        $serialized = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        $byteSize = mb_strlen($serialized);

        if ($byteSize <= $maxSize) {
            return $payload;
        }

        $truncated = mb_substr($serialized, 0, $maxSize - 100);
        $decoded = json_decode($truncated, true);

        return [
            '_truncated' => true,
            '_original_size' => $byteSize,
            '_data' => $decoded ?? $truncated,
        ];
    }
}
