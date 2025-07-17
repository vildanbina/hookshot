<?php

declare(strict_types=1);

namespace VildanBina\HookShot\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository as Config;
use InvalidArgumentException;
use VildanBina\HookShot\Contracts\RequestTrackerContract;

class CleanupCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'request-tracker:cleanup
                           {--driver= : Specific driver to cleanup (default: configured default driver)}
                           {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     */
    protected $description = 'Clean up old request tracking data based on retention policy';

    public function __construct(
        private readonly Config $config,
        private readonly RequestTrackerContract $requestTracker,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $driver = $this->option('driver');

        $this->info('Starting request tracker cleanup...');

        if ($dryRun) {
            $this->warn('DRY RUN: No data will actually be deleted');
        }

        try {
            if ($driver) {
                $count = $this->cleanupDriver($driver, $dryRun);
            } else {
                $defaultDriver = $this->config->get('request-tracker.default', 'database');
                $this->line("Using default driver: {$defaultDriver}");
                $count = $this->cleanupDriver($defaultDriver, $dryRun);
            }

            $this->info("Cleanup completed: {$count} records processed");

            return self::SUCCESS;
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (Exception $e) {
            $this->error("Cleanup failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    private function cleanupDriver(string $driver, bool $dryRun): int
    {
        $drivers = $this->config->get('request-tracker.drivers', []);

        if (! isset($drivers[$driver])) {
            throw new InvalidArgumentException("Driver '{$driver}' is not configured");
        }

        if ($dryRun) {
            $this->line("Would cleanup driver: {$driver}");

            return 0;
        }

        return $this->requestTracker->driver($driver)->cleanup();
    }
}
