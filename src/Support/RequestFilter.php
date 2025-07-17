<?php

declare(strict_types=1);

namespace VildanBina\HookShot\Support;

use Illuminate\Http\Request;

class RequestFilter
{
    public function __construct(private readonly array $config) {}

    public function shouldTrack(Request $request): bool
    {
        return $this->isEnabled()
            && $this->passesPathFilter($request)
            && $this->passesUserAgentFilter($request)
            && $this->passesSamplingFilter();
    }

    private function isEnabled(): bool
    {
        return $this->config['enabled'] ?? true;
    }

    private function passesPathFilter(Request $request): bool
    {
        $excludedPaths = $this->config['excluded_paths'] ?? [];

        foreach ($excludedPaths as $pattern) {
            if ($request->is($pattern)) {
                return false;
            }
        }

        return true;
    }

    private function passesUserAgentFilter(Request $request): bool
    {
        $excludedUserAgents = $this->config['excluded_user_agents'] ?? [];
        $userAgent = mb_strtolower($request->userAgent() ?? '');

        foreach ($excludedUserAgents as $pattern) {
            if (str_contains($userAgent, mb_strtolower($pattern))) {
                return false;
            }
        }

        return true;
    }

    private function passesSamplingFilter(): bool
    {
        $samplingRate = $this->config['sampling_rate'] ?? 1.0;

        if ($samplingRate >= 1.0) {
            return true;
        }

        return mt_rand() / mt_getrandmax() <= $samplingRate;
    }
}
