<?php

namespace Ermetix\LaravelLogger\Listeners;

use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

/**
 * Propagate request_id from parent context to job as parent_request_id.
 * 
 * When a job is processed, if there's a request_id in the context (propagated
 * from the parent request), it becomes the parent_request_id, and a new
 * request_id is generated for the job itself.
 */
class PropagateRequestIdToJob
{
    /**
     * Handle the event.
     */
    public function handle(JobProcessing $event): void
    {
        // Get the current request_id from context (propagated from parent)
        $parentRequestId = Context::get('request_id');

        if ($parentRequestId !== null) {
            // Set it as parent_request_id
            Context::add('parent_request_id', $parentRequestId);
        }

        // Generate a new request_id for this job
        $jobRequestId = (string) Str::uuid();
        Context::add('request_id', $jobRequestId);
    }
}
