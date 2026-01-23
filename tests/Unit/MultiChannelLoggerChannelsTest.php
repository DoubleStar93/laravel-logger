<?php

use Ermetix\LaravelLogger\Support\Logging\MultiChannelLogger;
use Ermetix\LaravelLogger\Support\Logging\Objects\GeneralLogObject;
use Illuminate\Support\Facades\Log;

test('MultiChannelLogger uses stack channels when configured', function () {
    config(['logging.channels.stack.channels' => ['opensearch', 'kafka', 'index_file']]);
    
    $logger = new MultiChannelLogger();
    $logObject = new GeneralLogObject(message: 'test', level: 'info');
    
    Log::shouldReceive('channel')
        ->with('opensearch')
        ->once()
        ->andReturnSelf();
    
    Log::shouldReceive('channel')
        ->with('kafka')
        ->once()
        ->andReturnSelf();
    
    Log::shouldReceive('channel')
        ->with('index_file')
        ->once()
        ->andReturnSelf();
    
    Log::shouldReceive('log')
        ->times(3)
        ->with('info', 'test', \Mockery::type('array'));
    
    $logger->log($logObject, defer: false);
});

test('MultiChannelLogger filters out empty and non-string channels', function () {
    config(['logging.channels.stack.channels' => ['opensearch', '', null, 'kafka', 123, 'index_file']]);
    
    $logger = new MultiChannelLogger();
    $logObject = new GeneralLogObject(message: 'test', level: 'info');
    
    // Should only call opensearch, kafka, index_file (empty, null, non-string filtered)
    Log::shouldReceive('channel')
        ->with('opensearch')
        ->once()
        ->andReturnSelf();
    
    Log::shouldReceive('channel')
        ->with('kafka')
        ->once()
        ->andReturnSelf();
    
    Log::shouldReceive('channel')
        ->with('index_file')
        ->once()
        ->andReturnSelf();
    
    Log::shouldReceive('log')
        ->times(3);
    
    $logger->log($logObject, defer: false);
});

test('MultiChannelLogger falls back to default channel when stack is empty', function () {
    config(['logging.channels.stack.channels' => []]);
    config(['logging.default' => 'single']);
    
    $logger = new MultiChannelLogger();
    $logObject = new GeneralLogObject(message: 'test', level: 'info');
    
    Log::shouldReceive('channel')
        ->with('single')
        ->once()
        ->andReturnSelf();
    
    Log::shouldReceive('log')
        ->once()
        ->with('info', 'test', \Mockery::type('array'));
    
    $logger->log($logObject, defer: false);
});

test('MultiChannelLogger falls back to stack when default is empty', function () {
    config(['logging.channels.stack.channels' => []]);
    config(['logging.default' => '']);
    
    $logger = new MultiChannelLogger();
    $logObject = new GeneralLogObject(message: 'test', level: 'info');
    
    Log::shouldReceive('channel')
        ->with('stack')
        ->once()
        ->andReturnSelf();
    
    Log::shouldReceive('log')
        ->once()
        ->with('info', 'test', \Mockery::type('array'));
    
    $logger->log($logObject, defer: false);
});

test('MultiChannelLogger falls back to stack when default is null', function () {
    config(['logging.channels.stack.channels' => []]);
    config(['logging.default' => null]);
    
    $logger = new MultiChannelLogger();
    $logObject = new GeneralLogObject(message: 'test', level: 'info');
    
    Log::shouldReceive('channel')
        ->with('stack')
        ->once()
        ->andReturnSelf();
    
    Log::shouldReceive('log')
        ->once()
        ->with('info', 'test', \Mockery::type('array'));
    
    $logger->log($logObject, defer: false);
});

test('MultiChannelLogger uses deferred logging when defer is true', function () {
    config(['logging.channels.stack.channels' => ['opensearch']]);
    
    $deferredLogger = \Mockery::mock(\Ermetix\LaravelLogger\Support\Logging\DeferredLogger::class);
    $deferredLogger->shouldReceive('defer')
        ->once()
        ->with('opensearch', 'info', 'test', \Mockery::type('array'));
    
    $logger = new MultiChannelLogger($deferredLogger);
    $logObject = new GeneralLogObject(message: 'test', level: 'info');
    
    $logger->log($logObject, defer: true);
});

test('MultiChannelLogger uses immediate logging when defer is false', function () {
    config(['logging.channels.stack.channels' => ['opensearch']]);
    
    $logger = new MultiChannelLogger();
    $logObject = new GeneralLogObject(message: 'test', level: 'info');
    
    Log::shouldReceive('channel')
        ->with('opensearch')
        ->once()
        ->andReturnSelf();
    
    Log::shouldReceive('log')
        ->once()
        ->with('info', 'test', \Mockery::type('array'));
    
    $logger->log($logObject, defer: false);
});

test('MultiChannelLogger uses immediate logging when deferredLogger is null', function () {
    config(['logging.channels.stack.channels' => ['opensearch']]);
    
    $logger = new MultiChannelLogger(null); // No deferred logger
    $logObject = new GeneralLogObject(message: 'test', level: 'info');
    
    Log::shouldReceive('channel')
        ->with('opensearch')
        ->once()
        ->andReturnSelf();
    
    Log::shouldReceive('log')
        ->once()
        ->with('info', 'test', \Mockery::type('array'));
    
    // Even with defer=true, should use immediate logging if deferredLogger is null
    $logger->log($logObject, defer: true);
});
