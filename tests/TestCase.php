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
}
