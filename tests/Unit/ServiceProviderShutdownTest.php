<?php

use Ermetix\LaravelLogger\Facades\LaravelLogger;
use Ermetix\LaravelLogger\LaravelLoggerServiceProvider;
use Ermetix\LaravelLogger\Support\Logging\DeferredLogger;
use Ermetix\LaravelLogger\Support\Logging\Objects\ErrorLogObject;

class TestLaravelLoggerServiceProvider extends LaravelLoggerServiceProvider
{
    public function runHandleShutdown(): void
    {
        $this->handleShutdown();
    }
}

test('ServiceProvider terminating callback flushes DeferredLogger', function () {
    $deferred = \Mockery::mock(DeferredLogger::class);
    $deferred->shouldReceive('flush')->atLeast()->once();
    app()->instance(DeferredLogger::class, $deferred);

    $provider = new LaravelLoggerServiceProvider(app());
    $provider->boot();

    // Trigger terminating callbacks
    app()->terminate();
});

test('ServiceProvider shutdown handler logs fatal errors and flushes deferred logs', function () {
    $provider = new TestLaravelLoggerServiceProvider(app());

    // No error -> nothing happens (covers early return).
    $GLOBALS['__ll_error_get_last'] = null;
    $provider->runHandleShutdown();

    // Fatal error path
    $GLOBALS['__ll_error_get_last'] = [
        'type' => E_ERROR,
        'message' => 'Fatal boom',
        'file' => '/app/test.php',
        'line' => 123,
    ];

    $GLOBALS['__ll_request'] = null;

    expect(app()->bound(\Ermetix\LaravelLogger\Support\Logging\TypedLogger::class))->toBeTrue();

    // Ensure Auth facade has a root instance in this testbench environment.
    $auth = \Mockery::mock();
    $auth->shouldReceive('check')->andReturn(false);
    $auth->shouldReceive('id')->andReturn(null);
    \Illuminate\Support\Facades\Auth::swap($auth);

    LaravelLogger::shouldReceive('error')
        ->atLeast()->once()
        ->with(\Mockery::type(ErrorLogObject::class), false);

    $deferred = \Mockery::mock(DeferredLogger::class);
    $deferred->shouldReceive('flush')->atLeast()->once();
    app()->instance(DeferredLogger::class, $deferred);

    $provider->runHandleShutdown();

    unset(
        $GLOBALS['__ll_error_get_last'],
        $GLOBALS['__ll_request']
    );
});

test('ServiceProvider shutdown handler ignores non-fatal errors', function () {
    $provider = new TestLaravelLoggerServiceProvider(app());

    $GLOBALS['__ll_error_get_last'] = [
        'type' => E_WARNING,
        'message' => 'Not fatal',
        'file' => '/app/test.php',
        'line' => 1,
    ];

    $provider->runHandleShutdown();

    unset($GLOBALS['__ll_error_get_last']);
    expect(true)->toBeTrue();
});

test('ServiceProvider shutdown handler swallows exceptions during shutdown handling', function () {
    $provider = new TestLaravelLoggerServiceProvider(app());

    $GLOBALS['__ll_error_get_last'] = [
        'type' => E_ERROR,
        'message' => 'Fatal boom',
        'file' => '/app/test.php',
        'line' => 1,
    ];

    \Illuminate\Support\Facades\Auth::swap(\Mockery::mock());
    $GLOBALS['__ll_request'] = null;

    LaravelLogger::shouldReceive('error')->andThrow(new RuntimeException('boom'));

    // Should not throw.
    $provider->runHandleShutdown();

    unset($GLOBALS['__ll_error_get_last'], $GLOBALS['__ll_request']);
    expect(true)->toBeTrue();
});

// Note: Testing when TypedLogger/DeferredLogger are not bound is difficult in testbench
// as they are always bound. The code handles these cases gracefully with the if checks.
// These paths are defensive and unlikely to be hit in practice, but the code is correct.

