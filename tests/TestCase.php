<?php

declare(strict_types=1);

namespace VildanBina\HookShot\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use VildanBina\HookShot\RequestTrackerServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function getPackageProviders($app): array
    {
        return [
            RequestTrackerServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        config()->set('request-tracker', [
            'enabled' => true,
            'default' => 'database',
            'drivers' => [
                'database' => [
                    'connection' => null,
                    'table' => 'request_tracker_logs',
                    'retention_days' => 30,
                ],
                'cache' => [
                    'store' => null,
                    'prefix' => 'request_tracker',
                    'retention_days' => 7,
                ],
                'file' => [
                    'path' => storage_path('app/test-requests'),
                    'format' => 'json',
                    'retention_days' => 30,
                ],
            ],
            'excluded_paths' => ['health-check', '_debugbar/*'],
            'excluded_user_agents' => ['pingdom', 'uptimerobot'],
            'sampling_rate' => 1.0,
            'max_payload_size' => 65536,
            'max_response_size' => 10240,
            'use_queue' => false,
        ]);
    }
}
