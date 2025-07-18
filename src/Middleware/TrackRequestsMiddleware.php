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
use VildanBina\HookShot\Support\RequestFilter;
use VildanBina\HookShot\Support\ResponseCapture;

class TrackRequestsMiddleware
{
    /**
     * Initialize the middleware with required dependencies.
     */
    public function __construct(
        private readonly RequestTrackerContract $tracker,
        private readonly Config $config,
        private readonly RequestFilter $filter,
        private readonly ResponseCapture $responseCapture,
    ) {}

    /**
     * Process the incoming request and start tracking.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('_hookshot_start', microtime(true));

        return $next($request);
    }

    /**
     * Complete request tracking after response is generated.
     */
    public function terminate(Request $request, Response $response): void
    {
        if (! $this->filter->shouldTrack($request)) {
            return;
        }

        $requestData = RequestData::fromRequest($request);
        $startTime = $request->attributes->get('_hookshot_start');

        if (! $startTime) {
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

    /**
     * Store the request data using queue or direct storage.
     */
    private function store(RequestData $requestData): void
    {
        if ($this->config->get('hookshot.use_queue', false)) {
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
