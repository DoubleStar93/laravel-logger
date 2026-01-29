<?php

namespace Ermetix\LaravelLogger\Listeners;

use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

/**
 * Propagate request_id and trace_id from parent context to job.
 * 
 * When a job is processed, if there's a request_id in the context (propagated
 * from the parent request), it becomes the parent_request_id, and a new
 * request_id is generated for the job itself.
 * The trace_id is preserved from the parent to keep all logs linked.
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
        $parentTraceId = Context::get('trace_id');

        if ($parentRequestId !== null) {
            // Set it as parent_request_id
            Context::add('parent_request_id', $parentRequestId);
        }

        // Generate a new request_id for this job
        $jobRequestId = (string) Str::uuid();
        Context::add('request_id', $jobRequestId);

        // Preserve trace_id from parent to keep all logs linked in distributed trace
        // trace_id should remain the same across jobs in the same trace
        if ($parentTraceId !== null) {
            Context::add('trace_id', $parentTraceId);
        } else {
            // Generate a new trace_id if not present (can be different from request_id)
            $newTraceId = (string) Str::uuid();
            Context::add('trace_id', $newTraceId);
        }
    }
}
