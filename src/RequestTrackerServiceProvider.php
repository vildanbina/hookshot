<?php

declare(strict_types=1);

namespace VildanBina\HookShot;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use VildanBina\HookShot\Console\Commands\CleanupCommand;
use VildanBina\HookShot\Contracts\RequestTrackerContract;
use VildanBina\HookShot\Middleware\TrackRequestsMiddleware;

class RequestTrackerServiceProvider extends ServiceProvider
{
    /**
     * Register the package services and bindings.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/hookshot.php',
            'hookshot'
        );

        $this->app->singleton(RequestTrackerContract::class, function ($app) {
            return new RequestTrackerManager($app);
        });

        $this->app->alias(RequestTrackerContract::class, 'hookshot');
    }

    /**
     * Bootstrap the package services and middleware.
     */
    public function boot(Router $router): void
    {
        $router->middlewareGroup('track-requests', [TrackRequestsMiddleware::class]);

        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
            $this->commands([CleanupCommand::class]);

            $this->publishes([
                __DIR__.'/../config/hookshot.php' => config_path('hookshot.php'),
            ], 'hookshot-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'hookshot-migrations');
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<string>
     */
    public function provides(): array
    {
        return [
            RequestTrackerContract::class,
            'hookshot',
        ];
    }
}
