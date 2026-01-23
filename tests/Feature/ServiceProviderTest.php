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
