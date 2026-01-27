<?php

use Ermetix\LaravelLogger\Listeners\FlushDeferredLogsForJob;
use Ermetix\LaravelLogger\Listeners\PropagateRequestIdToJob;
use Ermetix\LaravelLogger\Support\Logging\DeferredLogger;
use Illuminate\Console\Command;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Context;

// Concrete command class for testing serialization
class TestCommand extends Command
{
    protected $signature = 'test:command';
    public function getName(): ?string { return 'test:command'; }
}

class TestCommandWithoutName extends Command
{
    // No getName() method - will return null
}

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

test('LogJobEvents tracks job start time and logs on success', function () {
    \Ermetix\LaravelLogger\Facades\LaravelLogger::shouldReceive('job')
        ->once()
        ->with(\Mockery::type(\Ermetix\LaravelLogger\Support\Logging\Objects\JobLogObject::class), false)
        ->andReturnUsing(function ($object) {
            expect($object->status())->toBe('success');
            expect($object->level())->toBe('info');
            expect($object->durationMs())->not->toBeNull();
            expect($object->durationMs())->toBeGreaterThanOrEqual(0);
        });

    $listener = new \Ermetix\LaravelLogger\Listeners\LogJobEvents();

    $job = \Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
    $job->shouldReceive('getJobId')->andReturn('123');
    $job->shouldReceive('getQueue')->andReturn('default');
    $job->shouldReceive('attempts')->andReturn(1);
    $job->shouldReceive('payload')->andReturn([
        'displayName' => 'App\Jobs\TestJob',
    ]);
    // Mock uuid() to return null (method exists but returns null)
    if (method_exists($job, 'uuid')) {
        $job->shouldReceive('uuid')->andReturn(null);
    }

    // Track start
    $listener->handleJobProcessing(new JobProcessing('sync', $job));
    
    // Small delay to ensure duration > 0
    usleep(10000); // 10ms
    
    // Log completion
    $listener->handleJobProcessed(new JobProcessed('sync', $job));
});

test('LogJobEvents logs failure with error details', function () {
    \Ermetix\LaravelLogger\Facades\LaravelLogger::shouldReceive('job')
        ->once()
        ->with(\Mockery::type(\Ermetix\LaravelLogger\Support\Logging\Objects\JobLogObject::class), false)
        ->andReturnUsing(function ($object) {
            expect($object->status())->toBe('failed');
            expect($object->level())->toBe('error');
            expect($object->exitCode())->toBe(1);
            expect($object->output())->toContain('Test error');
        });

    $listener = new \Ermetix\LaravelLogger\Listeners\LogJobEvents();

    $job = \Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
    $job->shouldReceive('getJobId')->andReturn('123');
    $job->shouldReceive('getQueue')->andReturn('default');
    $job->shouldReceive('attempts')->andReturn(1);
    $job->shouldReceive('payload')->andReturn([
        'displayName' => 'App\Jobs\TestJob',
    ]);
    // Mock uuid() to return null (method exists but returns null)
    if (method_exists($job, 'uuid')) {
        $job->shouldReceive('uuid')->andReturn(null);
    }

    $exception = new RuntimeException('Test error', 1);

    // Track start
    $listener->handleJobProcessing(new JobProcessing('sync', $job));
    
    // Log failure
    $listener->handleJobFailed(new JobFailed('sync', $job, $exception));
});

test('LogJobEvents handles job without start time', function () {
    \Ermetix\LaravelLogger\Facades\LaravelLogger::shouldReceive('job')
        ->once()
        ->with(\Mockery::type(\Ermetix\LaravelLogger\Support\Logging\Objects\JobLogObject::class), false)
        ->andReturnUsing(function ($object) {
            expect($object->durationMs())->toBeNull();
        });

    $listener = new \Ermetix\LaravelLogger\Listeners\LogJobEvents();

    $job = \Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
    $job->shouldReceive('getJobId')->andReturn('123');
    $job->shouldReceive('getQueue')->andReturn('default');
    $job->shouldReceive('attempts')->andReturn(1);
    $job->shouldReceive('payload')->andReturn(['displayName' => 'App\Jobs\TestJob']);
    $job->shouldReceive('maxTries')->andReturn(null)->byDefault();
    $job->shouldReceive('uuid')->andReturn(null)->byDefault();

    // Log without tracking start (simulates edge case)
    $listener->handleJobProcessed(new JobProcessed('sync', $job));
});

test('LogJobEvents handles job with UUID', function () {
    \Ermetix\LaravelLogger\Facades\LaravelLogger::shouldReceive('job')
        ->once()
        ->with(\Mockery::type(\Ermetix\LaravelLogger\Support\Logging\Objects\JobLogObject::class), false)
        ->andReturnUsing(function ($object) {
            expect($object->jobId())->toBe('job-uuid-123');
        });

    $listener = new \Ermetix\LaravelLogger\Listeners\LogJobEvents();

    $job = \Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
    $job->shouldReceive('uuid')->andReturn('job-uuid-123');
    $job->shouldReceive('getQueue')->andReturn('default');
    $job->shouldReceive('attempts')->andReturn(1);
    $job->shouldReceive('payload')->andReturn(['displayName' => 'App\Jobs\TestJob']);
    $job->shouldReceive('maxTries')->andReturn(null)->byDefault();

    $listener->handleJobProcessing(new JobProcessing('sync', $job));
    $listener->handleJobProcessed(new JobProcessed('sync', $job));
});

test('LogJobEvents handles job with maxTries exception', function () {
    \Ermetix\LaravelLogger\Facades\LaravelLogger::shouldReceive('job')
        ->once();

    $listener = new \Ermetix\LaravelLogger\Listeners\LogJobEvents();

    $job = \Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
    $job->shouldReceive('getJobId')->andReturn('123');
    $job->shouldReceive('getQueue')->andReturn('default');
    $job->shouldReceive('attempts')->andReturn(1);
    $job->shouldReceive('payload')->andReturn(['displayName' => 'App\Jobs\TestJob']);
    $job->shouldReceive('maxTries')->andThrow(new RuntimeException('maxTries error'));
    $job->shouldReceive('uuid')->andReturn(null)->byDefault();

    $listener->handleJobProcessing(new JobProcessing('sync', $job));
    $listener->handleJobProcessed(new JobProcessed('sync', $job));
});


test('LogJobEvents handles job failed without exception', function () {
    \Ermetix\LaravelLogger\Facades\LaravelLogger::shouldReceive('job')
        ->once()
        ->with(\Mockery::type(\Ermetix\LaravelLogger\Support\Logging\Objects\JobLogObject::class), false)
        ->andReturnUsing(function ($object) {
            expect($object->status())->toBe('failed');
            expect($object->output())->toBe('Job failed');
            expect($object->exitCode())->toBe(1);
        });

    $listener = new \Ermetix\LaravelLogger\Listeners\LogJobEvents();

    $job = \Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
    $job->shouldReceive('getJobId')->andReturn('123');
    $job->shouldReceive('getQueue')->andReturn('default');
    $job->shouldReceive('attempts')->andReturn(1);
    $job->shouldReceive('payload')->andReturn(['displayName' => 'App\Jobs\TestJob']);
    $job->shouldReceive('maxTries')->andReturn(null)->byDefault();
    $job->shouldReceive('uuid')->andReturn(null)->byDefault();

    // JobFailed with null exception
    $reflection = new \ReflectionClass(JobFailed::class);
    $property = $reflection->getProperty('exception');
    $property->setAccessible(true);
    
    $event = new JobFailed('sync', $job, null);
    $property->setValue($event, null);

    $listener->handleJobProcessing(new JobProcessing('sync', $job));
    // No output captured, should use "Job failed" (line 174)
    $listener->handleJobFailed($event);
});

test('LogJobEvents captures and uses output from output buffering', function () {
    \Ermetix\LaravelLogger\Facades\LaravelLogger::shouldReceive('job')
        ->once()
        ->with(\Mockery::type(\Ermetix\LaravelLogger\Support\Logging\Objects\JobLogObject::class), false)
        ->andReturnUsing(function ($object) {
            expect($object->output())->toBe('Job output captured');
        });

    $listener = new \Ermetix\LaravelLogger\Listeners\LogJobEvents();

    $job = \Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
    $job->shouldReceive('getJobId')->andReturn('123');
    $job->shouldReceive('getQueue')->andReturn('default');
    $job->shouldReceive('attempts')->andReturn(1);
    $job->shouldReceive('payload')->andReturn(['displayName' => 'App\Jobs\TestJob']);
    $job->shouldReceive('maxTries')->andReturn(null)->byDefault();
    $job->shouldReceive('uuid')->andReturn(null)->byDefault();

    // Start job processing (starts output buffering)
    $listener->handleJobProcessing(new JobProcessing('sync', $job));
    
    // Simulate job output (this would normally come from the job execution)
    echo 'Job output captured';
    
    // Process job completion (captures output and logs)
    $listener->handleJobProcessed(new JobProcessed('sync', $job));
});

test('LogJobEvents uses default message when no output is captured for success', function () {
    \Ermetix\LaravelLogger\Facades\LaravelLogger::shouldReceive('job')
        ->once()
        ->with(\Mockery::type(\Ermetix\LaravelLogger\Support\Logging\Objects\JobLogObject::class), false)
        ->andReturnUsing(function ($object) {
            expect($object->output())->toBe('Job completed successfully');
        });

    $listener = new \Ermetix\LaravelLogger\Listeners\LogJobEvents();

    $job = \Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
    $job->shouldReceive('getJobId')->andReturn('123');
    $job->shouldReceive('getQueue')->andReturn('default');
    $job->shouldReceive('attempts')->andReturn(1);
    $job->shouldReceive('payload')->andReturn(['displayName' => 'App\Jobs\TestJob']);
    $job->shouldReceive('maxTries')->andReturn(null)->byDefault();
    $job->shouldReceive('uuid')->andReturn(null)->byDefault();

    $listener->handleJobProcessing(new JobProcessing('sync', $job));
    // No output produced
    $listener->handleJobProcessed(new JobProcessed('sync', $job));
});

test('LogJobEvents uses default "Job failed" message when failed without exception and no output', function () {
    \Ermetix\LaravelLogger\Facades\LaravelLogger::shouldReceive('job')
        ->once()
        ->with(\Mockery::type(\Ermetix\LaravelLogger\Support\Logging\Objects\JobLogObject::class), false)
        ->andReturnUsing(function ($object) {
            expect($object->status())->toBe('failed');
            expect($object->output())->toBe('Job failed'); // Linea 174
            expect($object->exitCode())->toBe(1);
        });

    $listener = new \Ermetix\LaravelLogger\Listeners\LogJobEvents();

    $job = \Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
    $job->shouldReceive('getJobId')->andReturn('123');
    $job->shouldReceive('getQueue')->andReturn('default');
    $job->shouldReceive('attempts')->andReturn(1);
    $job->shouldReceive('payload')->andReturn(['displayName' => 'App\Jobs\TestJob']);
    $job->shouldReceive('maxTries')->andReturn(null)->byDefault();
    $job->shouldReceive('uuid')->andReturn(null)->byDefault();

    // Use reflection to call logJob directly with errorMessage=null
    // This tests the else branch at line 174 where status='failed' and no output
    $reflection = new \ReflectionClass($listener);
    $method = $reflection->getMethod('logJob');
    $method->setAccessible(true);
    
    $listener->handleJobProcessing(new JobProcessing('sync', $job));
    // Call logJob directly with status='failed', errorMessage=null, and no captured output
    // This will trigger the else branch at line 174
    $method->invoke($listener, $job, 'failed', null, 1);
});

test('LogJobEvents handles job with payload job key', function () {
    \Ermetix\LaravelLogger\Facades\LaravelLogger::shouldReceive('job')
        ->once()
        ->with(\Mockery::type(\Ermetix\LaravelLogger\Support\Logging\Objects\JobLogObject::class), false)
        ->andReturnUsing(function ($object) {
            expect($object->job())->toBe('App\Jobs\CustomJob');
        });

    $listener = new \Ermetix\LaravelLogger\Listeners\LogJobEvents();

    $job = \Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
    $job->shouldReceive('getJobId')->andReturn('123');
    $job->shouldReceive('getQueue')->andReturn('default');
    $job->shouldReceive('attempts')->andReturn(1);
    $job->shouldReceive('payload')->andReturn(['job' => 'App\Jobs\CustomJob']);
    $job->shouldReceive('maxTries')->andReturn(null)->byDefault();
    $job->shouldReceive('uuid')->andReturn(null)->byDefault();

    $listener->handleJobProcessing(new JobProcessing('sync', $job));
    $listener->handleJobProcessed(new JobProcessed('sync', $job));
});

test('LogJobEvents handles logging exception gracefully', function () {
    \Ermetix\LaravelLogger\Facades\LaravelLogger::shouldReceive('job')
        ->once()
        ->andThrow(new RuntimeException('Logging failed'));

    // Mock logger() function if it exists
    if (function_exists('logger')) {
        \Illuminate\Support\Facades\Log::shouldReceive('error')
            ->once()
            ->with('Failed to log job event', \Mockery::type('array'));
    }

    $listener = new \Ermetix\LaravelLogger\Listeners\LogJobEvents();

    $job = \Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
    $job->shouldReceive('getJobId')->andReturn('123');
    $job->shouldReceive('getQueue')->andReturn('default');
    $job->shouldReceive('attempts')->andReturn(1);
    $job->shouldReceive('payload')->andReturn(['displayName' => 'App\Jobs\TestJob']);
    $job->shouldReceive('maxTries')->andReturn(null)->byDefault();
    $job->shouldReceive('uuid')->andReturn(null)->byDefault();

    // Should not throw exception, should handle gracefully
    $listener->handleJobProcessing(new JobProcessing('sync', $job));
    // Call directly - should not throw
    $listener->handleJobProcessed(new JobProcessed('sync', $job));
    
    expect(true)->toBeTrue(); // Test passes if no exception thrown
});

test('LogJobEvents extracts frequency from payload', function () {
    \Ermetix\LaravelLogger\Facades\LaravelLogger::shouldReceive('job')
        ->once()
        ->with(\Mockery::type(\Ermetix\LaravelLogger\Support\Logging\Objects\JobLogObject::class), false)
        ->andReturnUsing(function ($object) {
            expect($object->frequency())->toBe('*/5 * * * *');
        });

    $listener = new \Ermetix\LaravelLogger\Listeners\LogJobEvents();

    $job = \Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
    $job->shouldReceive('getJobId')->andReturn('123');
    $job->shouldReceive('getQueue')->andReturn('default');
    $job->shouldReceive('attempts')->andReturn(1);
    $job->shouldReceive('payload')->andReturn([
        'displayName' => 'App\Jobs\TestJob',
        'data' => [
            'frequency' => '*/5 * * * *',
        ],
    ]);
    $job->shouldReceive('maxTries')->andReturn(null)->byDefault();
    $job->shouldReceive('uuid')->andReturn(null)->byDefault();

    $listener->handleJobProcessing(new JobProcessing('sync', $job));
    $listener->handleJobProcessed(new JobProcessed('sync', $job));
});

test('LogJobEvents extracts frequency from cron field in payload', function () {
    \Ermetix\LaravelLogger\Facades\LaravelLogger::shouldReceive('job')
        ->once()
        ->with(\Mockery::type(\Ermetix\LaravelLogger\Support\Logging\Objects\JobLogObject::class), false)
        ->andReturnUsing(function ($object) {
            expect($object->frequency())->toBe('0 2 * * *');
        });

    $listener = new \Ermetix\LaravelLogger\Listeners\LogJobEvents();

    $job = \Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
    $job->shouldReceive('getJobId')->andReturn('123');
    $job->shouldReceive('getQueue')->andReturn('default');
    $job->shouldReceive('attempts')->andReturn(1);
    $job->shouldReceive('payload')->andReturn([
        'displayName' => 'App\Jobs\TestJob',
        'data' => [
            'cron' => '0 2 * * *',
        ],
    ]);
    $job->shouldReceive('maxTries')->andReturn(null)->byDefault();
    $job->shouldReceive('uuid')->andReturn(null)->byDefault();

    $listener->handleJobProcessing(new JobProcessing('sync', $job));
    $listener->handleJobProcessed(new JobProcessed('sync', $job));
});

test('LogJobEvents extracts frequency from schedule field in payload', function () {
    \Ermetix\LaravelLogger\Facades\LaravelLogger::shouldReceive('job')
        ->once()
        ->with(\Mockery::type(\Ermetix\LaravelLogger\Support\Logging\Objects\JobLogObject::class), false)
        ->andReturnUsing(function ($object) {
            expect($object->frequency())->toBe('daily');
        });

    $listener = new \Ermetix\LaravelLogger\Listeners\LogJobEvents();

    $job = \Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
    $job->shouldReceive('getJobId')->andReturn('123');
    $job->shouldReceive('getQueue')->andReturn('default');
    $job->shouldReceive('attempts')->andReturn(1);
    $job->shouldReceive('payload')->andReturn([
        'displayName' => 'App\Jobs\TestJob',
        'data' => [
            'schedule' => 'daily',
        ],
    ]);
    $job->shouldReceive('maxTries')->andReturn(null)->byDefault();
    $job->shouldReceive('uuid')->andReturn(null)->byDefault();

    $listener->handleJobProcessing(new JobProcessing('sync', $job));
    $listener->handleJobProcessed(new JobProcessed('sync', $job));
});

test('LogJobEvents handles getJobName with resolveName returning existing class', function () {
    \Ermetix\LaravelLogger\Facades\LaravelLogger::shouldReceive('job')
        ->once()
        ->with(\Mockery::type(\Ermetix\LaravelLogger\Support\Logging\Objects\JobLogObject::class), false);

    $listener = new \Ermetix\LaravelLogger\Listeners\LogJobEvents();

    $job = \Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
    $job->shouldReceive('getJobId')->andReturn('123');
    $job->shouldReceive('getQueue')->andReturn('default');
    $job->shouldReceive('attempts')->andReturn(1);
    $job->shouldReceive('payload')->andReturn([
        'data' => ['command' => serialize(new \Illuminate\Console\Command())],
    ]);
    $job->shouldReceive('resolveName')->andReturn(\Illuminate\Console\Command::class);
    $job->shouldReceive('maxTries')->andReturn(null)->byDefault();
    $job->shouldReceive('uuid')->andReturn(null)->byDefault();

    $listener->handleJobProcessing(new JobProcessing('sync', $job));
    $listener->handleJobProcessed(new JobProcessed('sync', $job));
});

test('LogJobEvents handles getJobName with resolveName returning non-existent class', function () {
    \Ermetix\LaravelLogger\Facades\LaravelLogger::shouldReceive('job')
        ->once()
        ->with(\Mockery::type(\Ermetix\LaravelLogger\Support\Logging\Objects\JobLogObject::class), false)
        ->andReturnUsing(function ($object) {
            // Should fallback to get_class($job)
            expect($object->job())->toContain('Mock');
        });

    $listener = new \Ermetix\LaravelLogger\Listeners\LogJobEvents();

    $job = \Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
    $job->shouldReceive('getJobId')->andReturn('123');
    $job->shouldReceive('getQueue')->andReturn('default');
    $job->shouldReceive('attempts')->andReturn(1);
    $job->shouldReceive('payload')->andReturn([]);
    $job->shouldReceive('resolveName')->andReturn('NonExistentClass');
    $job->shouldReceive('maxTries')->andReturn(null)->byDefault();
    $job->shouldReceive('uuid')->andReturn(null)->byDefault();

    $listener->handleJobProcessing(new JobProcessing('sync', $job));
    $listener->handleJobProcessed(new JobProcessed('sync', $job));
});

test('LogJobEvents detects cron job via Command unserialize', function () {
    \Ermetix\LaravelLogger\Facades\LaravelLogger::shouldReceive('job')
        ->once()
        ->with(\Mockery::type(\Ermetix\LaravelLogger\Support\Logging\Objects\JobLogObject::class), false)
        ->andReturnUsing(function ($object) {
            expect($object->command())->not->toBeNull();
        });

    $listener = new \Ermetix\LaravelLogger\Listeners\LogJobEvents();

    $serialized = serialize(new TestCommand());

    $job = \Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
    $job->shouldReceive('getJobId')->andReturn('123');
    $job->shouldReceive('getQueue')->andReturn('default');
    $job->shouldReceive('attempts')->andReturn(1);
    $job->shouldReceive('payload')->andReturn([
        'data' => ['command' => $serialized],
    ]);
    $job->shouldReceive('resolveName')->andReturn(null);
    $job->shouldReceive('maxTries')->andReturn(null)->byDefault();
    $job->shouldReceive('uuid')->andReturn(null)->byDefault();

    $listener->handleJobProcessing(new JobProcessing('sync', $job));
    $listener->handleJobProcessed(new JobProcessed('sync', $job));
});

test('LogJobEvents detects cron job via job name pattern matching', function () {
    \Ermetix\LaravelLogger\Facades\LaravelLogger::shouldReceive('job')
        ->once()
        ->with(\Mockery::type(\Ermetix\LaravelLogger\Support\Logging\Objects\JobLogObject::class), false)
        ->andReturnUsing(function ($object) {
            expect($object->job())->toContain('Command');
        });

    $listener = new \Ermetix\LaravelLogger\Listeners\LogJobEvents();

    $job = \Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
    $job->shouldReceive('getJobId')->andReturn('123');
    $job->shouldReceive('getQueue')->andReturn('default');
    $job->shouldReceive('attempts')->andReturn(1);
    $job->shouldReceive('payload')->andReturn([
        'displayName' => 'App\Console\Commands\TestCommand',
    ]);
    $job->shouldReceive('resolveName')->andReturn(null);
    $job->shouldReceive('maxTries')->andReturn(null)->byDefault();
    $job->shouldReceive('uuid')->andReturn(null)->byDefault();

    $listener->handleJobProcessing(new JobProcessing('sync', $job));
    $listener->handleJobProcessed(new JobProcessed('sync', $job));
});

test('LogJobEvents detects cron job via Illuminate\\Console\\ pattern', function () {
    \Ermetix\LaravelLogger\Facades\LaravelLogger::shouldReceive('job')
        ->once();

    $listener = new \Ermetix\LaravelLogger\Listeners\LogJobEvents();

    $job = \Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
    $job->shouldReceive('getJobId')->andReturn('123');
    $job->shouldReceive('getQueue')->andReturn('default');
    $job->shouldReceive('attempts')->andReturn(1);
    $job->shouldReceive('payload')->andReturn([
        'displayName' => 'Illuminate\\Console\\Scheduling\\ScheduleRunCommand',
    ]);
    $job->shouldReceive('resolveName')->andReturn(null);
    $job->shouldReceive('maxTries')->andReturn(null)->byDefault();
    $job->shouldReceive('uuid')->andReturn(null)->byDefault();

    $listener->handleJobProcessing(new JobProcessing('sync', $job));
    $listener->handleJobProcessed(new JobProcessed('sync', $job));
});

test('LogJobEvents handles getCommand with Command unserialize', function () {
    \Ermetix\LaravelLogger\Facades\LaravelLogger::shouldReceive('job')
        ->once()
        ->with(\Mockery::type(\Ermetix\LaravelLogger\Support\Logging\Objects\JobLogObject::class), false)
        ->andReturnUsing(function ($object) {
            expect($object->command())->toBe('php artisan test:command');
        });

    $listener = new \Ermetix\LaravelLogger\Listeners\LogJobEvents();

    $serialized = serialize(new TestCommand());

    $job = \Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
    $job->shouldReceive('getJobId')->andReturn('123');
    $job->shouldReceive('getQueue')->andReturn('default');
    $job->shouldReceive('attempts')->andReturn(1);
    $job->shouldReceive('payload')->andReturn([
        'data' => ['command' => $serialized],
    ]);
    $job->shouldReceive('resolveName')->andReturn(null);
    $job->shouldReceive('maxTries')->andReturn(null)->byDefault();
    $job->shouldReceive('uuid')->andReturn(null)->byDefault();

    $listener->handleJobProcessing(new JobProcessing('sync', $job));
    $listener->handleJobProcessed(new JobProcessed('sync', $job));
});

test('LogJobEvents handles getCommand with Command without name', function () {
    \Ermetix\LaravelLogger\Facades\LaravelLogger::shouldReceive('job')
        ->once()
        ->with(\Mockery::type(\Ermetix\LaravelLogger\Support\Logging\Objects\JobLogObject::class), false)
        ->andReturnUsing(function ($object) {
            expect($object->command())->toContain('php artisan');
        });

    $listener = new \Ermetix\LaravelLogger\Listeners\LogJobEvents();

    $serialized = serialize(new TestCommandWithoutName());

    $job = \Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
    $job->shouldReceive('getJobId')->andReturn('123');
    $job->shouldReceive('getQueue')->andReturn('default');
    $job->shouldReceive('attempts')->andReturn(1);
    $job->shouldReceive('payload')->andReturn([
        'data' => ['command' => $serialized],
    ]);
    $job->shouldReceive('resolveName')->andReturn(null);
    $job->shouldReceive('maxTries')->andReturn(null)->byDefault();
    $job->shouldReceive('uuid')->andReturn(null)->byDefault();

    $listener->handleJobProcessing(new JobProcessing('sync', $job));
    $listener->handleJobProcessed(new JobProcessed('sync', $job));
});

test('LogJobEvents handles getFrequency with Command unserialize returning null', function () {
    \Ermetix\LaravelLogger\Facades\LaravelLogger::shouldReceive('job')
        ->once()
        ->with(\Mockery::type(\Ermetix\LaravelLogger\Support\Logging\Objects\JobLogObject::class), false)
        ->andReturnUsing(function ($object) {
            expect($object->frequency())->toBeNull();
        });

    $listener = new \Ermetix\LaravelLogger\Listeners\LogJobEvents();

    $serialized = serialize(new TestCommand());

    $job = \Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
    $job->shouldReceive('getJobId')->andReturn('123');
    $job->shouldReceive('getQueue')->andReturn('default');
    $job->shouldReceive('attempts')->andReturn(1);
    $job->shouldReceive('payload')->andReturn([
        'data' => ['command' => $serialized],
    ]);
    $job->shouldReceive('resolveName')->andReturn(null);
    $job->shouldReceive('maxTries')->andReturn(null)->byDefault();
    $job->shouldReceive('uuid')->andReturn(null)->byDefault();

    $listener->handleJobProcessing(new JobProcessing('sync', $job));
    $listener->handleJobProcessed(new JobProcessed('sync', $job));
});

test('LogJobEvents handles getFrequency with unserialize error', function () {
    \Ermetix\LaravelLogger\Facades\LaravelLogger::shouldReceive('job')
        ->once()
        ->with(\Mockery::type(\Ermetix\LaravelLogger\Support\Logging\Objects\JobLogObject::class), false)
        ->andReturnUsing(function ($object) {
            expect($object->frequency())->toBeNull();
        });

    $listener = new \Ermetix\LaravelLogger\Listeners\LogJobEvents();

    $job = \Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
    $job->shouldReceive('getJobId')->andReturn('123');
    $job->shouldReceive('getQueue')->andReturn('default');
    $job->shouldReceive('attempts')->andReturn(1);
    $job->shouldReceive('payload')->andReturn([
        'displayName' => 'App\Console\Commands\TestCommand',
        'data' => ['command' => 'invalid-serialized-data'],
    ]);
    $job->shouldReceive('maxTries')->andReturn(null)->byDefault();
    $job->shouldReceive('uuid')->andReturn(null)->byDefault();

    $listener->handleJobProcessing(new JobProcessing('sync', $job));
    $listener->handleJobProcessed(new JobProcessed('sync', $job));
});

test('LogJobEvents handles getJobName with Command unserialize', function () {
    \Ermetix\LaravelLogger\Facades\LaravelLogger::shouldReceive('job')
        ->once()
        ->with(\Mockery::type(\Ermetix\LaravelLogger\Support\Logging\Objects\JobLogObject::class), false)
        ->andReturnUsing(function ($object) {
            expect($object->job())->toBe('test:command');
        });

    $listener = new \Ermetix\LaravelLogger\Listeners\LogJobEvents();

    $serialized = serialize(new TestCommand());

    $job = \Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
    $job->shouldReceive('getJobId')->andReturn('123');
    $job->shouldReceive('getQueue')->andReturn('default');
    $job->shouldReceive('attempts')->andReturn(1);
    $job->shouldReceive('payload')->andReturn([
        'data' => ['command' => $serialized],
    ]);
    $job->shouldReceive('resolveName')->andReturn(TestCommand::class);
    $job->shouldReceive('maxTries')->andReturn(null)->byDefault();
    $job->shouldReceive('uuid')->andReturn(null)->byDefault();

    $listener->handleJobProcessing(new JobProcessing('sync', $job));
    $listener->handleJobProcessed(new JobProcessed('sync', $job));
});

test('LogJobEvents handles getJobName with Command unserialize without name', function () {
    \Ermetix\LaravelLogger\Facades\LaravelLogger::shouldReceive('job')
        ->once()
        ->with(\Mockery::type(\Ermetix\LaravelLogger\Support\Logging\Objects\JobLogObject::class), false)
        ->andReturnUsing(function ($object) {
            // Should return class name when getName() returns null
            expect($object->job())->toContain('Command');
        });

    $listener = new \Ermetix\LaravelLogger\Listeners\LogJobEvents();

    $serialized = serialize(new TestCommandWithoutName());

    $job = \Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
    $job->shouldReceive('getJobId')->andReturn('123');
    $job->shouldReceive('getQueue')->andReturn('default');
    $job->shouldReceive('attempts')->andReturn(1);
    $job->shouldReceive('payload')->andReturn([
        'data' => ['command' => $serialized],
    ]);
    $job->shouldReceive('resolveName')->andReturn(TestCommand::class);
    $job->shouldReceive('maxTries')->andReturn(null)->byDefault();
    $job->shouldReceive('uuid')->andReturn(null)->byDefault();

    $listener->handleJobProcessing(new JobProcessing('sync', $job));
    $listener->handleJobProcessed(new JobProcessed('sync', $job));
});

test('LogJobEvents handles getJobName with resolveName returning class but unserialize fails', function () {
    \Ermetix\LaravelLogger\Facades\LaravelLogger::shouldReceive('job')
        ->once()
        ->with(\Mockery::type(\Ermetix\LaravelLogger\Support\Logging\Objects\JobLogObject::class), false)
        ->andReturnUsing(function ($object) {
            expect($object->job())->toBe('SomeCommandClass');
        });

    $listener = new \Ermetix\LaravelLogger\Listeners\LogJobEvents();

    $job = \Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
    $job->shouldReceive('getJobId')->andReturn('123');
    $job->shouldReceive('getQueue')->andReturn('default');
    $job->shouldReceive('attempts')->andReturn(1);
    $job->shouldReceive('payload')->andReturn([
        'data' => ['command' => 'invalid'],
    ]);
    $job->shouldReceive('resolveName')->andReturn('SomeCommandClass');
    $job->shouldReceive('maxTries')->andReturn(null)->byDefault();
    $job->shouldReceive('uuid')->andReturn(null)->byDefault();

    $listener->handleJobProcessing(new JobProcessing('sync', $job));
    $listener->handleJobProcessed(new JobProcessed('sync', $job));
});

test('LogJobEvents handles isCronJob with unserialize error', function () {
    \Ermetix\LaravelLogger\Facades\LaravelLogger::shouldReceive('job')
        ->once();

    $listener = new \Ermetix\LaravelLogger\Listeners\LogJobEvents();

    $job = \Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
    $job->shouldReceive('getJobId')->andReturn('123');
    $job->shouldReceive('getQueue')->andReturn('default');
    $job->shouldReceive('attempts')->andReturn(1);
    $job->shouldReceive('payload')->andReturn([
        'displayName' => 'App\Jobs\NormalJob',
        'data' => ['command' => 'invalid-serialized'],
    ]);
    $job->shouldReceive('resolveName')->andReturn(null);
    $job->shouldReceive('maxTries')->andReturn(null)->byDefault();
    $job->shouldReceive('uuid')->andReturn(null)->byDefault();

    $listener->handleJobProcessing(new JobProcessing('sync', $job));
    $listener->handleJobProcessed(new JobProcessed('sync', $job));
});

test('LogJobEvents handles getCommand with unserialize error', function () {
    \Ermetix\LaravelLogger\Facades\LaravelLogger::shouldReceive('job')
        ->once()
        ->with(\Mockery::type(\Ermetix\LaravelLogger\Support\Logging\Objects\JobLogObject::class), false)
        ->andReturnUsing(function ($object) {
            expect($object->command())->toBeNull();
        });

    $listener = new \Ermetix\LaravelLogger\Listeners\LogJobEvents();

    $job = \Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
    $job->shouldReceive('getJobId')->andReturn('123');
    $job->shouldReceive('getQueue')->andReturn('default');
    $job->shouldReceive('attempts')->andReturn(1);
    $job->shouldReceive('payload')->andReturn([
        'displayName' => 'App\Console\Commands\TestCommand',
        'data' => ['command' => 'invalid-serialized'],
    ]);
    $job->shouldReceive('resolveName')->andReturn(null);
    $job->shouldReceive('maxTries')->andReturn(null)->byDefault();
    $job->shouldReceive('uuid')->andReturn(null)->byDefault();

    $listener->handleJobProcessing(new JobProcessing('sync', $job));
    $listener->handleJobProcessed(new JobProcessed('sync', $job));
});

test('LogJobEvents handles getCommand with unserialize returning non-Command', function () {
    \Ermetix\LaravelLogger\Facades\LaravelLogger::shouldReceive('job')
        ->once()
        ->with(\Mockery::type(\Ermetix\LaravelLogger\Support\Logging\Objects\JobLogObject::class), false)
        ->andReturnUsing(function ($object) {
            expect($object->command())->toBeNull();
        });

    $listener = new \Ermetix\LaravelLogger\Listeners\LogJobEvents();

    $job = \Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
    $job->shouldReceive('getJobId')->andReturn('123');
    $job->shouldReceive('getQueue')->andReturn('default');
    $job->shouldReceive('attempts')->andReturn(1);
    $job->shouldReceive('payload')->andReturn([
        'displayName' => 'App\Console\Commands\TestCommand',
        'data' => ['command' => serialize(new \stdClass())],
    ]);
    $job->shouldReceive('resolveName')->andReturn(null);
    $job->shouldReceive('maxTries')->andReturn(null)->byDefault();
    $job->shouldReceive('uuid')->andReturn(null)->byDefault();

    $listener->handleJobProcessing(new JobProcessing('sync', $job));
    $listener->handleJobProcessed(new JobProcessed('sync', $job));
});


test('LogJobEvents handles getFrequency with Command unserialize returning non-Command', function () {
    \Ermetix\LaravelLogger\Facades\LaravelLogger::shouldReceive('job')
        ->once()
        ->with(\Mockery::type(\Ermetix\LaravelLogger\Support\Logging\Objects\JobLogObject::class), false)
        ->andReturnUsing(function ($object) {
            expect($object->frequency())->toBeNull();
        });

    $listener = new \Ermetix\LaravelLogger\Listeners\LogJobEvents();

    $job = \Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
    $job->shouldReceive('getJobId')->andReturn('123');
    $job->shouldReceive('getQueue')->andReturn('default');
    $job->shouldReceive('attempts')->andReturn(1);
    $job->shouldReceive('payload')->andReturn([
        'displayName' => 'App\Console\Commands\TestCommand',
        'data' => ['command' => serialize(new \stdClass())],
    ]);
    $job->shouldReceive('resolveName')->andReturn(null);
    $job->shouldReceive('maxTries')->andReturn(null)->byDefault();
    $job->shouldReceive('uuid')->andReturn(null)->byDefault();

    $listener->handleJobProcessing(new JobProcessing('sync', $job));
    $listener->handleJobProcessed(new JobProcessed('sync', $job));
});

test('LogJobEvents handles getJobName with resolveName returning null', function () {
    \Ermetix\LaravelLogger\Facades\LaravelLogger::shouldReceive('job')
        ->once()
        ->with(\Mockery::type(\Ermetix\LaravelLogger\Support\Logging\Objects\JobLogObject::class), false)
        ->andReturnUsing(function ($object) {
            // Should fallback to get_class($job)
            expect($object->job())->toContain('Mock');
        });

    $listener = new \Ermetix\LaravelLogger\Listeners\LogJobEvents();

    $job = \Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
    $job->shouldReceive('getJobId')->andReturn('123');
    $job->shouldReceive('getQueue')->andReturn('default');
    $job->shouldReceive('attempts')->andReturn(1);
    $job->shouldReceive('payload')->andReturn([]);
    $job->shouldReceive('resolveName')->andReturn(null);
    $job->shouldReceive('maxTries')->andReturn(null)->byDefault();
    $job->shouldReceive('uuid')->andReturn(null)->byDefault();

    $listener->handleJobProcessing(new JobProcessing('sync', $job));
    $listener->handleJobProcessed(new JobProcessed('sync', $job));
});

test('LogJobEvents handles getFrequency with empty payload data for cron job', function () {
    \Ermetix\LaravelLogger\Facades\LaravelLogger::shouldReceive('job')
        ->once()
        ->with(\Mockery::type(\Ermetix\LaravelLogger\Support\Logging\Objects\JobLogObject::class), false)
        ->andReturnUsing(function ($object) {
            expect($object->frequency())->toBeNull();
        });

    $listener = new \Ermetix\LaravelLogger\Listeners\LogJobEvents();

    $job = \Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
    $job->shouldReceive('getJobId')->andReturn('123');
    $job->shouldReceive('getQueue')->andReturn('default');
    $job->shouldReceive('attempts')->andReturn(1);
    $job->shouldReceive('payload')->andReturn([
        'displayName' => 'App\Console\Commands\TestCommand',
        'data' => [], // Empty data - will trigger all return statements in getFrequency
    ]);
    $job->shouldReceive('resolveName')->andReturn(null);
    $job->shouldReceive('maxTries')->andReturn(null)->byDefault();
    $job->shouldReceive('uuid')->andReturn(null)->byDefault();

    $listener->handleJobProcessing(new JobProcessing('sync', $job));
    $listener->handleJobProcessed(new JobProcessed('sync', $job));
});

test('LogJobEvents handles getJobName with resolveName returning class but unserialize returns non-Command', function () {
    \Ermetix\LaravelLogger\Facades\LaravelLogger::shouldReceive('job')
        ->once()
        ->with(\Mockery::type(\Ermetix\LaravelLogger\Support\Logging\Objects\JobLogObject::class), false)
        ->andReturnUsing(function ($object) {
            // Should return the class name from resolveName() when unserialize returns non-Command (line 193)
            expect($object->job())->toBe(\Illuminate\Console\Command::class);
        });

    $listener = new \Ermetix\LaravelLogger\Listeners\LogJobEvents();

    $job = \Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
    $job->shouldReceive('getJobId')->andReturn('123');
    $job->shouldReceive('getQueue')->andReturn('default');
    $job->shouldReceive('attempts')->andReturn(1);
    $job->shouldReceive('payload')->andReturn([
        'data' => ['command' => serialize(new \stdClass())], // Non-Command object
    ]);
    // resolveName() returns an existing class, but unserialize returns non-Command
    $job->shouldReceive('resolveName')->andReturn(\Illuminate\Console\Command::class);
    $job->shouldReceive('maxTries')->andReturn(null)->byDefault();
    $job->shouldReceive('uuid')->andReturn(null)->byDefault();

    $listener->handleJobProcessing(new JobProcessing('sync', $job));
    $listener->handleJobProcessed(new JobProcessed('sync', $job));
});

test('LogJobEvents handles getFrequency with frequency in payload for cron job', function () {
    \Ermetix\LaravelLogger\Facades\LaravelLogger::shouldReceive('job')
        ->once()
        ->with(\Mockery::type(\Ermetix\LaravelLogger\Support\Logging\Objects\JobLogObject::class), false)
        ->andReturnUsing(function ($object) {
            expect($object->frequency())->toBe('*/10 * * * *');
        });

    $listener = new \Ermetix\LaravelLogger\Listeners\LogJobEvents();

    $job = \Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
    $job->shouldReceive('getJobId')->andReturn('123');
    $job->shouldReceive('getQueue')->andReturn('default');
    $job->shouldReceive('attempts')->andReturn(1);
    $job->shouldReceive('payload')->andReturn([
        'displayName' => 'App\Console\Commands\TestCommand',
        'data' => ['frequency' => '*/10 * * * *'], // This should be returned directly
    ]);
    $job->shouldReceive('resolveName')->andReturn(null);
    $job->shouldReceive('maxTries')->andReturn(null)->byDefault();
    $job->shouldReceive('uuid')->andReturn(null)->byDefault();

    $listener->handleJobProcessing(new JobProcessing('sync', $job));
    $listener->handleJobProcessed(new JobProcessed('sync', $job));
});

test('LogJobEvents handles getFrequency with cron in payload for cron job', function () {
    \Ermetix\LaravelLogger\Facades\LaravelLogger::shouldReceive('job')
        ->once()
        ->with(\Mockery::type(\Ermetix\LaravelLogger\Support\Logging\Objects\JobLogObject::class), false)
        ->andReturnUsing(function ($object) {
            expect($object->frequency())->toBe('0 3 * * *');
        });

    $listener = new \Ermetix\LaravelLogger\Listeners\LogJobEvents();

    $job = \Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
    $job->shouldReceive('getJobId')->andReturn('123');
    $job->shouldReceive('getQueue')->andReturn('default');
    $job->shouldReceive('attempts')->andReturn(1);
    $job->shouldReceive('payload')->andReturn([
        'displayName' => 'App\Console\Commands\TestCommand',
        'data' => ['cron' => '0 3 * * *'], // This should be returned directly
    ]);
    $job->shouldReceive('resolveName')->andReturn(null);
    $job->shouldReceive('maxTries')->andReturn(null)->byDefault();
    $job->shouldReceive('uuid')->andReturn(null)->byDefault();

    $listener->handleJobProcessing(new JobProcessing('sync', $job));
    $listener->handleJobProcessed(new JobProcessed('sync', $job));
});

test('LogJobEvents handles getFrequency with schedule in payload for cron job', function () {
    \Ermetix\LaravelLogger\Facades\LaravelLogger::shouldReceive('job')
        ->once()
        ->with(\Mockery::type(\Ermetix\LaravelLogger\Support\Logging\Objects\JobLogObject::class), false)
        ->andReturnUsing(function ($object) {
            expect($object->frequency())->toBe('weekly');
        });

    $listener = new \Ermetix\LaravelLogger\Listeners\LogJobEvents();

    $job = \Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
    $job->shouldReceive('getJobId')->andReturn('123');
    $job->shouldReceive('getQueue')->andReturn('default');
    $job->shouldReceive('attempts')->andReturn(1);
    $job->shouldReceive('payload')->andReturn([
        'displayName' => 'App\Console\Commands\TestCommand',
        'data' => ['schedule' => 'weekly'], // This should be returned directly
    ]);
    $job->shouldReceive('resolveName')->andReturn(null);
    $job->shouldReceive('maxTries')->andReturn(null)->byDefault();
    $job->shouldReceive('uuid')->andReturn(null)->byDefault();

    $listener->handleJobProcessing(new JobProcessing('sync', $job));
    $listener->handleJobProcessed(new JobProcessed('sync', $job));
});

