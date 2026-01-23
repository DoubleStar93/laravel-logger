<?php

use Ermetix\LaravelLogger\Listeners\FlushDeferredLogsForJob;
use Ermetix\LaravelLogger\Listeners\PropagateRequestIdToJob;
use Ermetix\LaravelLogger\Support\Logging\DeferredLogger;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Context;

test('PropagateRequestIdToJob sets parent_request_id and generates new request_id', function () {
    Context::flush();
    Context::add('request_id', 'rid-parent');

    $job = \Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
    $event = new JobProcessing('sync', $job);

    (new PropagateRequestIdToJob())->handle($event);

    expect(Context::get('parent_request_id'))->toBe('rid-parent');
    expect(Context::get('request_id'))->not->toBe('rid-parent');
    expect(Context::get('request_id'))->not->toBeNull();
});

test('PropagateRequestIdToJob does not set parent_request_id when missing', function () {
    Context::flush();

    $job = \Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
    $event = new JobProcessing('sync', $job);

    (new PropagateRequestIdToJob())->handle($event);

    expect(Context::get('parent_request_id'))->toBeNull();
    expect(Context::get('request_id'))->not->toBeNull();
});

test('FlushDeferredLogsForJob flushes on processed and failed events', function () {
    $deferred = \Mockery::mock(DeferredLogger::class);
    $deferred->shouldReceive('flush')->twice();

    $listener = new FlushDeferredLogsForJob($deferred);

    $job = \Mockery::mock(\Illuminate\Contracts\Queue\Job::class);

    $listener->handle(new JobProcessed('sync', $job));
    $listener->handle(new JobFailed('sync', $job, new RuntimeException('x')));
});

