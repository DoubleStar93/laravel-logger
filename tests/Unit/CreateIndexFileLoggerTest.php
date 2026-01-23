<?php

use Ermetix\LaravelLogger\Logging\CreateIndexFileLogger;
use Ermetix\LaravelLogger\Logging\Handlers\IndexFileHandler;
use Monolog\Logger;

test('CreateIndexFileLogger creates logger with IndexFileHandler', function () {
    $creator = new CreateIndexFileLogger();
    
    $config = [
        'level' => 'info',
    ];
    
    $logger = $creator($config);
    
    expect($logger)->toBeInstanceOf(Logger::class);
    expect($logger->getName())->toBe('index_file');
    
    // Check that handler is IndexFileHandler
    $handlers = $logger->getHandlers();
    expect($handlers)->toHaveCount(1);
    expect($handlers[0])->toBeInstanceOf(IndexFileHandler::class);
});

test('CreateIndexFileLogger uses default level when not provided', function () {
    $creator = new CreateIndexFileLogger();
    
    $logger = $creator([]);
    
    $handlers = $logger->getHandlers();
    expect($handlers[0])->toBeInstanceOf(IndexFileHandler::class);
});

test('CreateIndexFileLogger adds processors', function () {
    $creator = new CreateIndexFileLogger();
    
    $logger = $creator(['level' => 'debug']);
    
    // Should have processors (PsrLogMessageProcessor and ContextLogProcessor)
    $processors = $logger->getProcessors();
    expect($processors)->not->toBeEmpty();
});
