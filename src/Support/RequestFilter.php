<?php

declare(strict_types=1);

namespace VildanBina\HookShot\Support;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Request;

/**
 * Determines whether requests should be tracked based on configuration rules.
 */
class RequestFilter
{
    public function __construct(private readonly Config $config) {}

    /**
     * Check if a request should be tracked.
     */
    public function shouldTrack(Request $request): bool
    {
        return $this->isEnabled()
            && $this->passesPathFilter($request)
            && $this->passesUserAgentFilter($request)
            && $this->passesSamplingFilter();
    }

    /**
     * Check if request tracking is globally enabled.
     */
    private function isEnabled(): bool
    {
        return $this->config->get('hookshot.enabled') ?? true;
    }

    /**
     * Check if request path is not in excluded paths.
     */
    private function passesPathFilter(Request $request): bool
    {
        $excludedPaths = $this->config->get('hookshot.excluded_paths') ?? [];

        foreach ($excludedPaths as $pattern) {
            if ($request->is($pattern)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if user agent is not in excluded list.
     */
    private function passesUserAgentFilter(Request $request): bool
    {
        $excludedUserAgents = $this->config->get('hookshot.excluded_user_agents') ?? [];
        $userAgent = mb_strtolower($request->userAgent() ?? '');

        foreach ($excludedUserAgents as $pattern) {
            if (str_contains($userAgent, mb_strtolower($pattern))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if request passes sampling rate filter.
     */
    private function passesSamplingFilter(): bool
    {
        $samplingRate = $this->config->get('hookshot.sampling_rate') ?? 1.0;

        if ($samplingRate >= 1.0) {
            return true;
        }

        return mt_rand() / mt_getrandmax() <= $samplingRate;
    }
}
