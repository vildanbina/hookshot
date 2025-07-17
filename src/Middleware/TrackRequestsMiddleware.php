<?php

declare(strict_types=1);

namespace VildanBina\HookShot\Middleware;

use Closure;
use Exception;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use VildanBina\HookShot\Contracts\RequestTrackerContract;
use VildanBina\HookShot\Data\RequestData;
use VildanBina\HookShot\Events\RequestCaptured;
use VildanBina\HookShot\Jobs\StoreRequestDataJob;
use VildanBina\HookShot\Support\DataExtractor;
use VildanBina\HookShot\Support\RequestFilter;
use VildanBina\HookShot\Support\ResponseCapture;

class TrackRequestsMiddleware
{
    private readonly RequestFilter $filter;

    private readonly ResponseCapture $responseCapture;

    private readonly DataExtractor $dataExtractor;

    public function __construct(
        private readonly RequestTrackerContract $tracker,
        private readonly Config $config
    ) {
        $requestTrackerConfig = $this->config->get('request-tracker', []);
        $this->filter = new RequestFilter($requestTrackerConfig);
        $this->responseCapture = new ResponseCapture($requestTrackerConfig);
        $this->dataExtractor = new DataExtractor($requestTrackerConfig);
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->filter->shouldTrack($request)) {
            return $next($request);
        }

        $request->attributes->set('_request_tracker_start', microtime(true));
        $request->attributes->set('_request_tracker_data', RequestData::fromRequest($request, $this->dataExtractor));

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $startTime = $request->attributes->get('_request_tracker_start');
        $requestData = $request->attributes->get('_request_tracker_data');

        if (! $startTime || ! $requestData) {
            return;
        }

        $executionTime = microtime(true) - $startTime;

        $finalRequestData = $requestData->withResponse(
            status: $response->getStatusCode(),
            headers: $this->responseCapture->getHeaders($response),
            body: $this->responseCapture->getBody($response),
            executionTime: $executionTime
        );

        $event = new RequestCaptured($request, $finalRequestData);
        event($event);

        $this->store($finalRequestData);
    }

    private function store(RequestData $requestData): void
    {
        if ($this->config->get('request-tracker.use_queue', false)) {
            StoreRequestDataJob::dispatch($requestData->toArray());

            return;
        }

        try {
            $this->tracker->store($requestData);
        } catch (Exception $e) {
            logger()->error('Request tracking failed', [
                'exception' => $e->getMessage(),
                'request_id' => $requestData->id,
            ]);
        }
    }
}
