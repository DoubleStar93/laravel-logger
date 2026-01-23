<?php

use Ermetix\LaravelLogger\Support\Logging\DeferredLogger;
use Ermetix\LaravelLogger\Support\Logging\MultiChannelLogger;
use Ermetix\LaravelLogger\Support\Logging\Objects\GeneralLogObject;
use Illuminate\Support\Facades\Log;

test('multi channel logger logs to multiple channels', function () {
    Log::shouldReceive('channel')
        ->once()
        ->with('single')
        ->andReturnSelf();
    
    Log::shouldReceive('log')
        ->once()
        ->with('info', 'test_message', \Mockery::on(function ($context) {
            return isset($context['log_index']) && $context['log_index'] === 'general_log';
        }));
    
    $logger = new MultiChannelLogger(
        deferredLogger: new DeferredLogger(),
    );
    
    $logObject = new GeneralLogObject(
        message: 'test_message',
        event: 'test_event',
        level: 'info',
    );
    
    $logger->log($logObject, defer: false);
});

test('multi channel logger defers logs when requested', function () {
    $deferredLogger = new DeferredLogger();
    
    $logger = new MultiChannelLogger(
        deferredLogger: $deferredLogger,
    );
    
    $logObject = new GeneralLogObject(
        message: 'test_message',
        event: 'test_event',
        level: 'info',
    );
    
    Log::shouldReceive('channel')->never();
    
    $logger->log($logObject, defer: true);
    
    expect($deferredLogger->count())->toBe(1);
});

test('multi channel logger uses stack channels from config', function () {
    config(['logging.channels.stack.channels' => ['single', 'daily']]);
    
    Log::shouldReceive('channel')
        ->once()
        ->with('single')
        ->andReturnSelf();
    
    Log::shouldReceive('channel')
        ->once()
        ->with('daily')
        ->andReturnSelf();
    
    Log::shouldReceive('log')
        ->times(2)
        ->with('info', 'test_message', \Mockery::any());
    
    $logger = new MultiChannelLogger(
        deferredLogger: new DeferredLogger(),
    );
    
    $logObject = new GeneralLogObject(
        message: 'test_message',
        event: 'test_event',
        level: 'info',
    );
    
    $logger->log($logObject, defer: false);
});

test('multi channel logger falls back to logging.default when stack is empty', function () {
    config([
        'logging.channels.stack.channels' => [],
        'logging.default' => 'daily',
    ]);

    Log::shouldReceive('channel')
        ->once()
        ->with('daily')
        ->andReturnSelf();

    Log::shouldReceive('log')
        ->once()
        ->with('info', 'test_message', \Mockery::any());

    $logger = new MultiChannelLogger(deferredLogger: new DeferredLogger());
    $logger->log(new GeneralLogObject(message: 'test_message', level: 'info'), defer: false);
});

test('multi channel logger falls back to stack channel name when default is missing', function () {
    config([
        'logging.channels.stack.channels' => [],
        'logging.default' => null,
    ]);

    Log::shouldReceive('channel')
        ->once()
        ->with('stack')
        ->andReturnSelf();

    Log::shouldReceive('log')
        ->once()
        ->with('info', 'test_message', \Mockery::any());

    $logger = new MultiChannelLogger(deferredLogger: new DeferredLogger());
    $logger->log(new GeneralLogObject(message: 'test_message', level: 'info'), defer: false);
});
