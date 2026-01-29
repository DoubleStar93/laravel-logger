<?php

use Ermetix\LaravelLogger\Support\Logging\DeferredLogger;
use Ermetix\LaravelLogger\Support\Logging\MultiChannelLogger;
use Ermetix\LaravelLogger\Support\Logging\Objects\GeneralLogObject;
use Illuminate\Support\Facades\Context;
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

test('multi channel logger ensures request_id and trace_id are always present in context', function () {
    $capturedContext = null;
    Log::shouldReceive('channel')
        ->andReturnSelf();
    Log::shouldReceive('log')
        ->atLeast()->once()
        ->andReturnUsing(function ($level, $message, $context) use (&$capturedContext) {
            $capturedContext = $context;
        });

    Context::flush();
    Context::add('request_id', 'ctx-request-123');
    Context::add('trace_id', 'ctx-trace-456');

    $logger = new MultiChannelLogger(deferredLogger: new DeferredLogger());
    $logger->log(new GeneralLogObject(message: 'test_message', level: 'info'), defer: false);

    expect($capturedContext)->not->toBeNull();
    expect($capturedContext)->toHaveKeys(['request_id', 'trace_id']);
    expect($capturedContext['request_id'])->not->toBeEmpty();
    expect($capturedContext['trace_id'])->not->toBeEmpty();
    expect($capturedContext['trace_id'])->toBe($capturedContext['request_id']);
});

test('multi channel logger uses request_id and trace_id from Context when provided', function () {
    // Subclass that returns values from "Context" to cover getRequestIdFromContext/getTraceIdFromContext branches
    $logger = new class(new DeferredLogger()) extends MultiChannelLogger {
        protected function getRequestIdFromContext(): ?string
        {
            return 'from-context-request';
        }
        protected function getTraceIdFromContext(): ?string
        {
            return 'from-context-trace';
        }
    };

    $capturedContext = null;
    Log::shouldReceive('channel')->andReturnSelf();
    Log::shouldReceive('log')
        ->atLeast()->once()
        ->andReturnUsing(function ($level, $message, $context) use (&$capturedContext) {
            $capturedContext = $context;
        });

    $logger->log(new GeneralLogObject(message: 'test_message', level: 'info'), defer: false);

    expect($capturedContext['request_id'])->toBe('from-context-request');
    expect($capturedContext['trace_id'])->toBe('from-context-trace');
});

test('multi channel logger falls back to generated IDs when Context throws', function () {
    $contextMock = Mockery::mock(stdClass::class);
    $contextMock->shouldReceive('get')->andThrow(new \RuntimeException('Context unavailable'));
    Context::swap($contextMock);

    $capturedContext = null;
    Log::shouldReceive('channel')->andReturnSelf();
    Log::shouldReceive('log')
        ->atLeast()->once()
        ->andReturnUsing(function ($level, $message, $context) use (&$capturedContext) {
            $capturedContext = $context;
        });

    $logger = new MultiChannelLogger(deferredLogger: new DeferredLogger());
    $logger->log(new GeneralLogObject(message: 'test_message', level: 'info'), defer: false);

    expect($capturedContext)->toHaveKeys(['request_id', 'trace_id']);
    expect($capturedContext['request_id'])->not->toBeEmpty();
    expect($capturedContext['trace_id'])->toBe($capturedContext['request_id']);
});
