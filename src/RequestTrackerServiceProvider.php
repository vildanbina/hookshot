<?php

declare(strict_types=1);

namespace VildanBina\HookShot;

use Illuminate\Contracts\Http\Kernel;
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
            __DIR__.'/../config/request-tracker.php',
            'request-tracker'
        );

        $this->app->singleton(RequestTrackerContract::class, function ($app) {
            return new RequestTrackerManager($app);
        });

        $this->app->alias(RequestTrackerContract::class, 'request-tracker');
    }

    /**
     * Bootstrap the package services and middleware.
     */
    public function boot(Router $router): void
    {
        $router->middlewareGroup('track-requests', [TrackRequestsMiddleware::class]);

        if (config('request-tracker.auto_track', true)) {
            $this->app->make(Kernel::class)->pushMiddleware(TrackRequestsMiddleware::class);
        }

        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
            $this->commands([CleanupCommand::class]);

            $this->publishes([
                __DIR__.'/../config/request-tracker.php' => config_path('request-tracker.php'),
            ], 'request-tracker-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'request-tracker-migrations');
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
            'request-tracker',
        ];
    }
}
