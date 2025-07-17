<?php

declare(strict_types=1);

namespace VildanBina\HookShot;

use Illuminate\Routing\Router;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider;
use VildanBina\HookShot\Console\Commands\CleanupCommand;
use VildanBina\HookShot\Contracts\RequestTrackerContract;
use VildanBina\HookShot\Middleware\TrackRequestsMiddleware;

class RequestTrackerServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/request-tracker.php',
            'request-tracker'
        );

        $this->app->singleton(RequestTrackerContract::class, function ($app) {
            return new RequestTrackerManager($app);
        });

        $this->app->alias(RequestTrackerContract::class, 'request-tracker');
    }

    public function boot(Router $router): void
    {
        // Always register middleware group for manual usage
        $router->middlewareGroup('track-requests', [TrackRequestsMiddleware::class]);

        // Optionally push to global middleware stack (default: true for backward compatibility)
        if (config('request-tracker.auto_track', true)) {
            $this->app->make(Kernel::class)->pushMiddleware(TrackRequestsMiddleware::class);
        }

        // Console-only features
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
            $this->commands([CleanupCommand::class]);

            $this->publishes([
                __DIR__ . '/../config/request-tracker.php' => config_path('request-tracker.php'),
            ], 'request-tracker-config');

            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'request-tracker-migrations');
        }
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            RequestTrackerContract::class,
            'request-tracker',
        ];
    }
}
