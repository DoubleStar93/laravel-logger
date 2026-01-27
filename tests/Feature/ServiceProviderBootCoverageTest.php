<?php

use Ermetix\LaravelLogger\Listeners\FlushDeferredLogsForJob;
use Ermetix\LaravelLogger\Listeners\LogModelEvents;
use Ermetix\LaravelLogger\Listeners\PropagateRequestIdToJob;
use Ermetix\LaravelLogger\LaravelLoggerServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;

test('service provider binds FlushDeferredLogsForJob via container factory', function () {
    $listener = app(FlushDeferredLogsForJob::class);
    expect($listener)->toBeInstanceOf(FlushDeferredLogsForJob::class);
});

test('service provider binds PropagateRequestIdToJob via container factory', function () {
    $listener = app(PropagateRequestIdToJob::class);
    expect($listener)->toBeInstanceOf(PropagateRequestIdToJob::class);
});

test('service provider registers a shutdown callback that calls handleShutdown', function () {
    $GLOBALS['__ll_capture_shutdown'] = true;
    $GLOBALS['__ll_shutdown_callbacks'] = [];

    $provider = new LaravelLoggerServiceProvider(app());
    $provider->boot();

    expect($GLOBALS['__ll_shutdown_callbacks'])->not->toBeEmpty();

    // Call the last registered callback (it just calls handleShutdown()).
    $GLOBALS['__ll_error_get_last'] = null;
    $cb = $GLOBALS['__ll_shutdown_callbacks'][count($GLOBALS['__ll_shutdown_callbacks']) - 1];
    $cb();

    unset($GLOBALS['__ll_capture_shutdown'], $GLOBALS['__ll_shutdown_callbacks'], $GLOBALS['__ll_error_get_last']);
});

test('service provider wildcard eloquent listeners forward to LogModelEvents', function () {
    config(['laravel-logger.orm.model_events_enabled' => true]);

    $mock = \Mockery::mock(LogModelEvents::class);
    $mock->shouldReceive('created')->once();
    $mock->shouldReceive('updated')->once();
    $mock->shouldReceive('deleted')->once();
    app()->instance(LogModelEvents::class, $mock);

    $model = new class extends Model {
        protected $table = 'users';
        public $timestamps = false;
        protected $guarded = [];
    };

    Event::dispatch('eloquent.created: App\\Models\\User', [$model]);
    Event::dispatch('eloquent.updated: App\\Models\\User', [$model]);
    Event::dispatch('eloquent.deleted: App\\Models\\User', [$model]);
});

test('service provider handles LogDatabaseQuery listener errors gracefully', function () {
    config(['laravel-logger.orm.enabled' => true]);
    
    // Create a mock listener that throws an exception
    $mockListener = \Mockery::mock(\Ermetix\LaravelLogger\Listeners\LogDatabaseQuery::class);
    $mockListener->shouldReceive('handle')->andThrow(new \RuntimeException('Test error'));
    app()->instance(\Ermetix\LaravelLogger\Listeners\LogDatabaseQuery::class, $mockListener);
    
    // Mock Log facade to verify error logging (at least once, may be called multiple times)
    \Illuminate\Support\Facades\Log::shouldReceive('error')
        ->atLeast()->once()
        ->with('Failed to log database query', \Mockery::type('array'));
    
    // Re-register the service provider to set up the listener
    $provider = new LaravelLoggerServiceProvider(app());
    $provider->boot();
    
    // Trigger a QueryExecuted event
    $event = new \Illuminate\Database\Events\QueryExecuted(
        sql: 'SELECT * FROM users',
        bindings: [],
        time: 1.0,
        connection: \Illuminate\Support\Facades\DB::connection(),
    );
    
    // Dispatch the event - the catch block should handle the error
    \Illuminate\Support\Facades\Event::dispatch($event);
});

test('service provider binds LogJobEvents via container factory', function () {
    $listener = app(\Ermetix\LaravelLogger\Listeners\LogJobEvents::class);
    expect($listener)->toBeInstanceOf(\Ermetix\LaravelLogger\Listeners\LogJobEvents::class);
});

test('service provider handles LogJobEvents JobProcessing errors gracefully', function () {
    config(['laravel-logger.job.enabled' => true]);
    
    // Create a mock listener that throws an exception
    $mockListener = \Mockery::mock(\Ermetix\LaravelLogger\Listeners\LogJobEvents::class);
    $mockListener->shouldReceive('handleJobProcessing')->andThrow(new \RuntimeException('Test error'));
    app()->instance(\Ermetix\LaravelLogger\Listeners\LogJobEvents::class, $mockListener);
    
    // Re-register the service provider to set up the listener
    $provider = new LaravelLoggerServiceProvider(app());
    $provider->boot();
    
    // Trigger a JobProcessing event
    $job = \Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
    // PropagateRequestIdToJob also listens to JobProcessing and calls payload()
    $job->shouldReceive('payload')->andReturn([])->byDefault();
    $event = new \Illuminate\Queue\Events\JobProcessing('sync', $job);
    
    // Dispatch the event - the catch block should handle the error silently
    \Illuminate\Support\Facades\Event::dispatch($event);
    
    // Test passes if no exception is thrown
    expect(true)->toBeTrue();
});

test('service provider handles LogJobEvents JobProcessed errors gracefully', function () {
    config(['laravel-logger.job.enabled' => true]);
    
    // Create a mock listener that throws an exception
    $mockListener = \Mockery::mock(\Ermetix\LaravelLogger\Listeners\LogJobEvents::class);
    $mockListener->shouldReceive('handleJobProcessed')->andThrow(new \RuntimeException('Test error'));
    app()->instance(\Ermetix\LaravelLogger\Listeners\LogJobEvents::class, $mockListener);
    
    // Re-register the service provider to set up the listener
    $provider = new LaravelLoggerServiceProvider(app());
    $provider->boot();
    
    // Trigger a JobProcessed event
    $job = \Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
    $event = new \Illuminate\Queue\Events\JobProcessed('sync', $job);
    
    // Dispatch the event - the catch block should handle the error silently
    \Illuminate\Support\Facades\Event::dispatch($event);
    
    // Test passes if no exception is thrown
    expect(true)->toBeTrue();
});

test('service provider handles LogJobEvents JobFailed errors gracefully', function () {
    config(['laravel-logger.job.enabled' => true]);
    
    // Create a mock listener that throws an exception
    $mockListener = \Mockery::mock(\Ermetix\LaravelLogger\Listeners\LogJobEvents::class);
    $mockListener->shouldReceive('handleJobFailed')->andThrow(new \RuntimeException('Test error'));
    app()->instance(\Ermetix\LaravelLogger\Listeners\LogJobEvents::class, $mockListener);
    
    // Re-register the service provider to set up the listener
    $provider = new LaravelLoggerServiceProvider(app());
    $provider->boot();
    
    // Trigger a JobFailed event
    $job = \Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
    $exception = new \RuntimeException('Job failed');
    $event = new \Illuminate\Queue\Events\JobFailed('sync', $job, $exception);
    
    // Dispatch the event - the catch block should handle the error silently
    \Illuminate\Support\Facades\Event::dispatch($event);
    
    // Test passes if no exception is thrown
    expect(true)->toBeTrue();
});

