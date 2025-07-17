<?php

declare(strict_types=1);

namespace VildanBina\HookShot\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use VildanBina\HookShot\Contracts\RequestTrackerContract;
use VildanBina\HookShot\Data\RequestData;

class StoreRequestDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly array $requestDataArray
    ) {}

    public function handle(RequestTrackerContract $tracker): void
    {
        $requestData = RequestData::fromArray($this->requestDataArray);

        try {
            $tracker->store($requestData);
        } catch (Exception $e) {
            logger()->error('Queued request tracking failed', [
                'exception' => $e->getMessage(),
                'request_id' => $requestData->id,
            ]);
        }
    }
}
