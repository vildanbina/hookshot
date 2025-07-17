<?php

declare(strict_types=1);

namespace VildanBina\HookShot\Support;

use Illuminate\Http\Request;

class DataExtractor
{
    private const SENSITIVE_HEADERS = ['authorization', 'cookie', 'set-cookie', 'x-api-key', 'x-auth-token'];

    public function __construct(private readonly array $config) {}

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

    private function limitPayloadSize(mixed $payload): mixed
    {
        $maxSize = $this->config['max_payload_size'] ?? 65536;
        $serialized = json_encode($payload);

        if (mb_strlen($serialized) <= $maxSize) {
            return $payload;
        }

        return [
            '_truncated' => true,
            '_original_size' => mb_strlen($serialized),
            '_data' => json_decode(mb_substr($serialized, 0, $maxSize - 100), true) ?? mb_substr($serialized, 0, $maxSize - 100),
        ];
    }
}
