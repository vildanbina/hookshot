<?php

declare(strict_types=1);

namespace VildanBina\HookShot\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository as Config;
use VildanBina\HookShot\Contracts\RequestTrackerContract;

/**
 * Console command for cleaning up old request tracking data.
 */
class CleanupCommand extends Command
{
    /**
     * The command signature.
     */
    protected $signature = 'hookshot:cleanup
                            {--driver= : Specify storage driver to clean up}
                            {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The command description.
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
        $dryRun = (bool) $this->option('dry-run');
        $driver = is_string($this->option('driver')) ? $this->option('driver') : null;

        $this->info('Starting request tracker cleanup...');

        if ($dryRun) {
            $this->info('DRY RUN MODE - No data will be deleted');
        }

        try {
            $availableDrivers = $this->getAvailableDrivers();
            $driverToCleanup = $this->getDriverToCleanup($driver, $availableDrivers);

            if ($dryRun) {
                $this->info('Would delete 0 old request records');
            } else {
                $count = $this->requestTracker->driver($driverToCleanup)->cleanup();
                $this->line("Successfully deleted {$count} old request records");
            }

            return Command::SUCCESS;
        } catch (Exception $e) {
            $this->error("Cleanup failed: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }

    /**
     * Get all available drivers from configuration.
     *
     * @return array<string>
     *
     * @throws Exception
     */
    private function getAvailableDrivers(): array
    {
        /** @var array<string> $availableDrivers */
        $availableDrivers = array_keys($this->config->get('hookshot.drivers', []));

        if (empty($availableDrivers)) {
            throw new Exception('No storage drivers configured.');
        }

        return $availableDrivers;
    }

    /**
     * Get the default driver or validate the provided driver.
     */
    private function getDriverToCleanup(?string $driverOption, array $availableDrivers): string
    {
        if ($driverOption) {
            if (! in_array($driverOption, $availableDrivers)) {
                throw new Exception("Driver '{$driverOption}' is not configured.");
            }

            return $driverOption;
        }

        $defaultDriver = $this->config->get('hookshot.default', 'database');

        if (! in_array($defaultDriver, $availableDrivers)) {
            throw new Exception("Default driver '{$defaultDriver}' is not configured.");
        }

        return $defaultDriver;
    }
}
