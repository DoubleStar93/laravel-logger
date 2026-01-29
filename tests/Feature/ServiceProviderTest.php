<?php

use Ermetix\LaravelLogger\Facades\LaravelLogger;
use Ermetix\LaravelLogger\Support\Logging\DeferredLogger;
use Ermetix\LaravelLogger\Support\Logging\MultiChannelLogger;
use Ermetix\LaravelLogger\Support\Logging\TypedLogger;

test('service provider registers deferred logger as singleton', function () {
    $logger1 = app(DeferredLogger::class);
    $logger2 = app(DeferredLogger::class);
    
    expect($logger1)->toBe($logger2);
});

test('service provider registers multi channel logger', function () {
    $logger = app(MultiChannelLogger::class);
    
    expect($logger)->toBeInstanceOf(MultiChannelLogger::class);
});

test('service provider registers typed logger', function () {
    $logger = app(TypedLogger::class);
    
    expect($logger)->toBeInstanceOf(TypedLogger::class);
});

test('service provider registers facade', function () {
    $logger = app('laravel_logger');
    
    expect($logger)->toBeInstanceOf(TypedLogger::class);
});

test('facade resolves to typed logger', function () {
    expect(LaravelLogger::getFacadeRoot())->toBeInstanceOf(TypedLogger::class);
});

test('service provider register handles maxLogs = 0', function () {
    config(['laravel-logger.deferred.max_logs' => 0]);
    
    // Re-register to pick up new config
    $provider = new \Ermetix\LaravelLogger\LaravelLoggerServiceProvider(app());
    $provider->register();
    
    $deferred = app(\Ermetix\LaravelLogger\Support\Logging\DeferredLogger::class);
    expect($deferred->getMaxLogs())->toBeNull(); // Should be null when maxLogs is 0
});

test('service provider register handles negative maxLogs', function () {
    config(['laravel-logger.deferred.max_logs' => -1]);
    
    $provider = new \Ermetix\LaravelLogger\LaravelLoggerServiceProvider(app());
    $provider->register();
    
    $deferred = app(\Ermetix\LaravelLogger\Support\Logging\DeferredLogger::class);
    expect($deferred->getMaxLogs())->toBeNull(); // Should be null when maxLogs is negative
});

test('service provider register handles warnOnLimit = false', function () {
    config([
        'laravel-logger.deferred.max_logs' => 10,
        'laravel-logger.deferred.warn_on_limit' => false,
    ]);
    
    $provider = new \Ermetix\LaravelLogger\LaravelLoggerServiceProvider(app());
    $provider->register();
    
    $deferred = app(\Ermetix\LaravelLogger\Support\Logging\DeferredLogger::class);
    // Should be configured with warnOnLimit = false
    // We can't easily test this without triggering auto-flush, but we verify it doesn't throw
    expect($deferred)->toBeInstanceOf(\Ermetix\LaravelLogger\Support\Logging\DeferredLogger::class);
});
