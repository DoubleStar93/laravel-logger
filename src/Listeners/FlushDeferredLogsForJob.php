<?php

namespace Ermetix\LaravelLogger\Listeners;

use Ermetix\LaravelLogger\Support\Logging\DeferredLogger;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobFailed;

/**
 * Flush deferred logs after a job is processed (success or failure).
 */
class FlushDeferredLogsForJob
{
    public function __construct(
        private readonly DeferredLogger $deferredLogger,
    ) {}

    /**
     * Handle the event.
     */
    public function handle(JobProcessed|JobFailed $event): void
    {
        $this->deferredLogger->flush();
    }
}
