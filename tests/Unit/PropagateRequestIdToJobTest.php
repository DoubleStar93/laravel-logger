<?php

use Ermetix\LaravelLogger\Listeners\PropagateRequestIdToJob;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

beforeEach(function () {
    Context::flush();
});

test('PropagateRequestIdToJob propagates request_id to parent_request_id and generates new request_id', function () {
    $listener = new PropagateRequestIdToJob();
    
    // Set initial request_id in context (from parent request)
    Context::add('request_id', 'parent-request-123');
    
    // Create mock job event
    $job = new class {
        public function getJobId() { return 'job-123'; }
    };
    $event = new JobProcessing('connection', $job);
    
    $listener->handle($event);
    
    // Should have parent_request_id set to original request_id
    expect(Context::get('parent_request_id'))->toBe('parent-request-123');
    
    // Should have new request_id generated
    $newRequestId = Context::get('request_id');
    expect($newRequestId)->not->toBe('parent-request-123');
    expect($newRequestId)->toBeString();
    expect(Str::isUuid($newRequestId))->toBeTrue();
});

test('PropagateRequestIdToJob generates new request_id even when parent request_id is missing', function () {
    $listener = new PropagateRequestIdToJob();
    
    // No parent request_id in context
    Context::flush();
    
    $job = new class {
        public function getJobId() { return 'job-123'; }
    };
    $event = new JobProcessing('connection', $job);
    
    $listener->handle($event);
    
    // Should not have parent_request_id
    expect(Context::get('parent_request_id'))->toBeNull();
    
    // Should have new request_id generated
    $newRequestId = Context::get('request_id');
    expect($newRequestId)->toBeString();
    expect(Str::isUuid($newRequestId))->toBeTrue();
});

test('PropagateRequestIdToJob preserves trace_id from parent', function () {
    $listener = new PropagateRequestIdToJob();
    
    // Set initial request_id and trace_id in context (from parent request)
    Context::add('request_id', 'parent-request-123');
    Context::add('trace_id', 'parent-trace-456');
    
    $job = new class {
        public function getJobId() { return 'job-123'; }
    };
    $event = new JobProcessing('connection', $job);
    
    $listener->handle($event);
    
    // Should preserve trace_id from parent
    expect(Context::get('trace_id'))->toBe('parent-trace-456');
    
    // Should have new request_id generated
    $newRequestId = Context::get('request_id');
    expect($newRequestId)->not->toBe('parent-request-123');
    expect($newRequestId)->toBeString();
    expect(Str::isUuid($newRequestId))->toBeTrue();
});

test('PropagateRequestIdToJob generates trace_id when parent trace_id is missing', function () {
    $listener = new PropagateRequestIdToJob();
    
    // Set only request_id, no trace_id
    Context::add('request_id', 'parent-request-123');
    
    $job = new class {
        public function getJobId() { return 'job-123'; }
    };
    $event = new JobProcessing('connection', $job);
    
    $listener->handle($event);
    
    // Should have new request_id generated
    $newRequestId = Context::get('request_id');
    expect($newRequestId)->not->toBe('parent-request-123');
    expect($newRequestId)->toBeString();
    expect(Str::isUuid($newRequestId))->toBeTrue();
    
    // Should have trace_id generated (same as new request_id to keep them linked)
    $traceId = Context::get('trace_id');
    expect($traceId)->toBe($newRequestId);
    expect($traceId)->toBeString();
    expect(Str::isUuid($traceId))->toBeTrue();
});
