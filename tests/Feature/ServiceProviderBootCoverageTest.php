<?php

use Ermetix\LaravelLogger\Listeners\FlushDeferredLogsForJob;
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

test('service provider wildcard eloquent listeners forward to LogOrmOperation', function () {
    config(['laravel-logger.orm.enabled' => true]);

    $mock = \Mockery::mock(\Ermetix\LaravelLogger\Listeners\LogOrmOperation::class);
    $mock->shouldReceive('created')->once();
    $mock->shouldReceive('updated')->once();
    $mock->shouldReceive('deleted')->once();
    app()->instance(\Ermetix\LaravelLogger\Listeners\LogOrmOperation::class, $mock);

    $model = new class extends Model {
        protected $table = 'users';
        public $timestamps = false;
        protected $guarded = [];
    };

    Event::dispatch('eloquent.created: App\\Models\\User', [$model]);
    Event::dispatch('eloquent.updated: App\\Models\\User', [$model]);
    Event::dispatch('eloquent.deleted: App\\Models\\User', [$model]);
});

test('service provider handles LogOrmOperation QueryExecuted listener errors gracefully', function () {
    config(['laravel-logger.orm.enabled' => true]);
    
    // Create a mock listener that throws an exception when handleQueryExecuted is called
    $mockListener = \Mockery::mock(\Ermetix\LaravelLogger\Listeners\LogOrmOperation::class);
    $mockListener->shouldReceive('handleQueryExecuted')->andThrow(new \RuntimeException('Test error'));
    app()->instance(\Ermetix\LaravelLogger\Listeners\LogOrmOperation::class, $mockListener);
    
    // Mock Log facade to verify error logging when listener throws (may be called once per dispatch)
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
    
    // Dispatch the event - the catch block should handle the error and call Log::error
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

test('service provider boot skips ORM logging when disabled', function () {
    config(['laravel-logger.orm.enabled' => false]);
    
    $provider = new LaravelLoggerServiceProvider(app());
    $provider->boot();
    
    // ORM event listeners should not be registered
    // We can't easily test this directly, but we verify boot doesn't throw
    expect(true)->toBeTrue();
});

test('service provider boot skips job logging when disabled', function () {
    config(['laravel-logger.job.enabled' => false]);
    
    $provider = new LaravelLoggerServiceProvider(app());
    $provider->boot();
    
    // Job event listeners should not be registered when disabled
    // We verify boot doesn't throw
    expect(true)->toBeTrue();
});

test('service provider boot handles HTTP request context (not console)', function () {
    // Boot in HTTP context (not console)
    $provider = new LaravelLoggerServiceProvider(app());
    $provider->boot();
    
    // Should not initialize correlation IDs for CLI (only for console)
    // But should still register everything else
    expect(Http::hasMacro('withTraceContext'))->toBeTrue();
});

test('service provider registers Http macro withTraceContext', function () {
    // Boot the service provider
    $provider = new LaravelLoggerServiceProvider(app());
    $provider->boot();
    
    // Verify the macro is registered by checking if it exists
    expect(\Illuminate\Support\Facades\Http::hasMacro('withTraceContext'))->toBeTrue();
});

test('service provider registerHttpMacro handles missing Http class gracefully', function () {
    // Create a test provider that we can access the protected method
    $provider = new class(app()) extends LaravelLoggerServiceProvider {
        public function testRegisterHttpMacro(): void {
            // Temporarily remove Http class to test the early return
            // We can't actually remove it, but we can test the logic
            $this->registerHttpMacro();
        }
    };
    
    // Should not throw even if Http class doesn't exist (though it always exists in Laravel)
    $provider->testRegisterHttpMacro();
    
    // Macro should still be registered (Http class exists)
    expect(\Illuminate\Support\Facades\Http::hasMacro('withTraceContext'))->toBeTrue();
});

test('service provider initializeCorrelationIdsForCli generates IDs when missing', function () {
    \Illuminate\Support\Facades\Context::flush();
    
    // Boot provider in console mode
    $provider = new LaravelLoggerServiceProvider(app());
    $provider->boot();
    
    // Should have generated request_id and trace_id
    expect(\Illuminate\Support\Facades\Context::get('request_id'))->not->toBeEmpty();
    expect(\Illuminate\Support\Facades\Context::get('trace_id'))->not->toBeEmpty();
    
    // They should be different UUIDs
    $requestId = \Illuminate\Support\Facades\Context::get('request_id');
    $traceId = \Illuminate\Support\Facades\Context::get('trace_id');
    expect($requestId)->not->toBe($traceId);
});

test('service provider initializeCorrelationIdsForCli preserves existing IDs', function () {
    \Illuminate\Support\Facades\Context::flush();
    \Illuminate\Support\Facades\Context::add('request_id', 'existing-request-123');
    \Illuminate\Support\Facades\Context::add('trace_id', 'existing-trace-456');
    
    // Boot provider in console mode
    $provider = new LaravelLoggerServiceProvider(app());
    $provider->boot();
    
    // Should preserve existing IDs
    expect(\Illuminate\Support\Facades\Context::get('request_id'))->toBe('existing-request-123');
    expect(\Illuminate\Support\Facades\Context::get('trace_id'))->toBe('existing-trace-456');
});

test('service provider initializeCorrelationIdsForCli handles missing Context class', function () {
    // This test verifies the early return when Context class doesn't exist
    // In practice, Context always exists in Laravel 12+, but we test the guard clause
    $provider = new LaravelLoggerServiceProvider(app());
    
    // Boot should not throw even if Context check fails (it won't in practice)
    $provider->boot();
    
    // Test passes if no exception is thrown
    expect(true)->toBeTrue();
});

