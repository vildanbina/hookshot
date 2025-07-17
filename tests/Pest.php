<?php

declare(strict_types=1);

uses(
    VildanBina\HookShot\Tests\TestCase::class,
    Illuminate\Foundation\Testing\RefreshDatabase::class,
)->in('Feature', 'Unit');

// Helpers
function requestData(array $overrides = []): array
{
    return array_merge([
        'id' => '550e8400-e29b-41d4-a716-446655440000',
        'method' => 'POST',
        'url' => 'https://app.test/api/users',
        'path' => 'api/users',
        'headers' => ['accept' => ['application/json']],
        'query' => ['page' => '1'],
        'payload' => ['name' => 'John', 'email' => 'john@example.com'],
        'ip' => '192.168.1.100',
        'user_agent' => 'TestAgent/1.0',
        'user_id' => 123,
        'metadata' => ['route_name' => 'users.store'],
        'timestamp' => '2024-01-15T10:30:00Z',
        'execution_time' => 0.245,
        'response_status' => 201,
        'response_headers' => ['content-type' => ['application/json']],
        'response_body' => ['id' => 456, 'name' => 'John'],
    ], $overrides);
}

function mockRequest(array $overrides = []): Illuminate\Http\Request
{
    $defaults = [
        'method' => 'POST',
        'url' => 'https://app.test/api/users',
        'path' => 'api/users',
        'headers' => ['Accept' => 'application/json'],
        'query' => ['page' => '1'],
        'content' => json_encode(['name' => 'John', 'email' => 'john@example.com']),
    ];

    $data = array_merge($defaults, $overrides);

    $request = Illuminate\Http\Request::create(
        $data['url'],
        $data['method'],
        $data['query'] ?? [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => $data['headers']['Accept'] ?? 'application/json',
        ],
        $data['content'] ?? null
    );

    foreach ($data['headers'] ?? [] as $key => $value) {
        $request->headers->set($key, $value);
    }

    return $request;
}

function createTestUser(int $id = 123, string $name = 'Test User'): Illuminate\Contracts\Auth\Authenticatable
{
    return new class($id, $name) implements Illuminate\Contracts\Auth\Authenticatable
    {
        public function __construct(public int $id, public string $name) {}

        public function getAuthIdentifierName(): string
        {
            return 'id';
        }

        public function getAuthIdentifier(): mixed
        {
            return $this->id;
        }

        public function getAuthPassword(): string
        {
            return '';
        }

        public function getAuthPasswordName(): string
        {
            return 'password';
        }

        public function getRememberToken(): ?string
        {
            return null;
        }

        public function setRememberToken($value): void {}

        public function getRememberTokenName(): string
        {
            return '';
        }

        public function getKey()
        {
            return $this->id;
        }
    };
}
