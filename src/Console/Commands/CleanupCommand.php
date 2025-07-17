<?php

declare(strict_types=1);

namespace VildanBina\HookShot\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository as Config;
use InvalidArgumentException;
use VildanBina\HookShot\Contracts\RequestTrackerContract;

/**
 * Console command for cleaning up old request tracking data.
 */
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

    /**
     * Create a new command instance.
     */
    public function __construct(
        private readonly Config $config,
        private readonly RequestTrackerContract $requestTracker,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $driver = $this->option('driver');

        $this->info('Starting request tracker cleanup...');

        if ($dryRun) {
            $this->info('DRY RUN MODE - No data will be deleted');
        }

        try {
            if ($driver) {
                $deleted = $this->cleanupSpecificDriver($driver, $dryRun);
            } else {
                $deleted = $this->cleanupDefaultDriver($dryRun);
            }

            if ($dryRun) {
                $this->info("Would delete {$deleted} old request records");
            } else {
                $this->info("Successfully deleted {$deleted} old request records");
            }

            return Command::SUCCESS;
        } catch (Exception $e) {
            $this->error("Cleanup failed: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }

    /**
     * Clean up using a specific storage driver.
     */
    private function cleanupSpecificDriver(string $driver, bool $dryRun): int
    {
        $availableDrivers = array_keys($this->config->get('request-tracker.drivers', []));

        if (! in_array($driver, $availableDrivers)) {
            throw new InvalidArgumentException("Driver '{$driver}' is not configured");
        }

        $this->info("Using driver: {$driver}");

        if ($dryRun) {
            return 0;
        }

        return $this->requestTracker->driver($driver)->cleanup();
    }

    /**
     * Clean up using the default configured driver.
     */
    private function cleanupDefaultDriver(bool $dryRun): int
    {
        $defaultDriver = $this->config->get('request-tracker.default', 'database');
        $this->info("Using default driver: {$defaultDriver}");

        if ($dryRun) {
            return 0;
        }

        return $this->requestTracker->cleanup();
    }
}
