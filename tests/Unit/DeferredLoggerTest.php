<?php

use Ermetix\LaravelLogger\Support\Logging\DeferredLogger;
use Illuminate\Support\Facades\Log;

test('deferred logger accumulates logs in memory', function () {
    $logger = new DeferredLogger();
    
    expect($logger->count())->toBe(0);
    
    $logger->defer('single', 'info', 'Test message', ['key' => 'value']);
    
    expect($logger->count())->toBe(1);
});

test('deferred logger can clear logs without writing', function () {
    $logger = new DeferredLogger();
    
    $logger->defer('single', 'info', 'Test message', ['key' => 'value']);
    $logger->defer('single', 'error', 'Another message', ['key2' => 'value2']);
    
    expect($logger->count())->toBe(2);
    
    $logger->clear();
    
    expect($logger->count())->toBe(0);
});

test('deferred logger flushes logs to channels', function () {
    Log::shouldReceive('channel')
        ->once()
        ->with('single')
        ->andReturnSelf();
    
    Log::shouldReceive('log')
        ->once()
        ->with('info', 'Test message', ['key' => 'value']);
    
    $logger = new DeferredLogger();
    $logger->defer('single', 'info', 'Test message', ['key' => 'value']);
    $logger->flush();
    
    expect($logger->count())->toBe(0);
});

test('deferred logger handles multiple logs', function () {
    // With batch processing, logs are grouped by channel, so channel() is called once per channel
    $mockLogger = \Mockery::mock(\Psr\Log\LoggerInterface::class);
    
    Log::shouldReceive('channel')
        ->once()
        ->with('single')
        ->andReturn($mockLogger);
    
    // Logs are processed individually when no batchable handlers are present
    $mockLogger->shouldReceive('log')
        ->once()
        ->with('info', 'Message 1', ['key1' => 'value1']);
    
    $mockLogger->shouldReceive('log')
        ->once()
        ->with('error', 'Message 2', ['key2' => 'value2']);
    
    $logger = new DeferredLogger();
    $logger->defer('single', 'info', 'Message 1', ['key1' => 'value1']);
    $logger->defer('single', 'error', 'Message 2', ['key2' => 'value2']);
    
    expect($logger->count())->toBe(2);
    
    $logger->flush();
    
    expect($logger->count())->toBe(0);
});

test('deferred logger flush does nothing when empty', function () {
    $logger = new DeferredLogger();
    
    Log::shouldReceive('channel')->never();
    
    $logger->flush();
    
    expect($logger->count())->toBe(0);
});

test('deferred logger groups logs by channel for batch processing', function () {
    $mockLogger1 = \Mockery::mock(\Psr\Log\LoggerInterface::class);
    $mockLogger2 = \Mockery::mock(\Psr\Log\LoggerInterface::class);
    
    Log::shouldReceive('channel')
        ->once()
        ->with('single')
        ->andReturn($mockLogger1);
    
    Log::shouldReceive('channel')
        ->once()
        ->with('daily')
        ->andReturn($mockLogger2);
    
    $mockLogger1->shouldReceive('log')
        ->once()
        ->with('info', 'Message 1', ['key1' => 'value1']);
    
    $mockLogger1->shouldReceive('log')
        ->once()
        ->with('error', 'Message 2', ['key2' => 'value2']);
    
    $mockLogger2->shouldReceive('log')
        ->once()
        ->with('info', 'Message 3', ['key3' => 'value3']);
    
    $logger = new DeferredLogger();
    $logger->defer('single', 'info', 'Message 1', ['key1' => 'value1']);
    $logger->defer('single', 'error', 'Message 2', ['key2' => 'value2']);
    $logger->defer('daily', 'info', 'Message 3', ['key3' => 'value3']);
    
    expect($logger->count())->toBe(3);
    
    $logger->flush();
    
    expect($logger->count())->toBe(0);
});

test('deferred logger uses batch processing when batchable handlers are available', function () {
    $mockBatchableHandler = \Mockery::mock(
        \Ermetix\LaravelLogger\Logging\Contracts\BatchableHandler::class,
        \Monolog\Handler\HandlerInterface::class
    );
    
    $mockLogger = \Mockery::mock(\Monolog\Logger::class);
    $mockLogger->shouldReceive('getHandlers')
        ->andReturn([$mockBatchableHandler]);
    
    Log::shouldReceive('channel')
        ->once()
        ->with('opensearch')
        ->andReturn($mockLogger);
    
    // Batchable handler should receive writeBatch with 2 records
    $mockBatchableHandler->shouldReceive('writeBatch')
        ->once()
        ->with(\Mockery::on(function ($records) {
            return count($records) === 2
                && $records[0] instanceof \Monolog\LogRecord
                && $records[1] instanceof \Monolog\LogRecord;
        }));
    
    $logger = new DeferredLogger();
    $logger->defer('opensearch', 'info', 'Message 1', ['log_index' => 'api_log', 'key1' => 'value1']);
    $logger->defer('opensearch', 'error', 'Message 2', ['log_index' => 'general_log', 'key2' => 'value2']);
    
    $logger->flush();
    
    expect($logger->count())->toBe(0);
});

test('deferred logger falls back to individual writes when batch fails', function () {
    $mockBatchableHandler = \Mockery::mock(
        \Ermetix\LaravelLogger\Logging\Contracts\BatchableHandler::class,
        \Monolog\Handler\HandlerInterface::class
    );
    
    $mockLogger = \Mockery::mock(\Monolog\Logger::class);
    $mockLogger->shouldReceive('getHandlers')
        ->andReturn([$mockBatchableHandler]);
    
    Log::shouldReceive('channel')
        ->once()
        ->with('opensearch')
        ->andReturn($mockLogger);
    
    // Batch fails, should fall back to individual writes
    $mockBatchableHandler->shouldReceive('writeBatch')
        ->once()
        ->andThrow(new \Exception('Batch failed'));
    
    $mockBatchableHandler->shouldReceive('handle')
        ->twice()
        ->with(\Mockery::type(\Monolog\LogRecord::class));
    
    $logger = new DeferredLogger();
    $logger->defer('opensearch', 'info', 'Message 1', ['log_index' => 'api_log']);
    $logger->defer('opensearch', 'error', 'Message 2', ['log_index' => 'general_log']);
    
    $logger->flush();
    
    expect($logger->count())->toBe(0);
});

test('deferred logger processes non-batchable handlers individually', function () {
    $mockBatchableHandler = \Mockery::mock(
        \Ermetix\LaravelLogger\Logging\Contracts\BatchableHandler::class,
        \Monolog\Handler\HandlerInterface::class
    );
    
    $mockNonBatchableHandler = \Mockery::mock(\Monolog\Handler\HandlerInterface::class);
    
    $mockLogger = \Mockery::mock(\Monolog\Logger::class);
    $mockLogger->shouldReceive('getHandlers')
        ->andReturn([$mockBatchableHandler, $mockNonBatchableHandler]);
    
    Log::shouldReceive('channel')
        ->once()
        ->with('opensearch')
        ->andReturn($mockLogger);
    
    // Batchable handler receives batch
    $mockBatchableHandler->shouldReceive('writeBatch')
        ->once()
        ->with(\Mockery::on(function ($records) {
            return count($records) === 1;
        }));
    
    // Non-batchable handler receives individual records
    $mockNonBatchableHandler->shouldReceive('handle')
        ->once()
        ->with(\Mockery::type(\Monolog\LogRecord::class));
    
    $logger = new DeferredLogger();
    $logger->defer('opensearch', 'info', 'Message 1', ['log_index' => 'api_log']);
    
    $logger->flush();
    
    expect($logger->count())->toBe(0);
});

test('deferred logger falls back to standard logging on exception', function () {
    Log::shouldReceive('channel')
        ->once()
        ->with('test')
        ->andThrow(new \Exception('Channel error'));
    
    // Should fall back to standard logging
    Log::shouldReceive('channel')
        ->once()
        ->with('test')
        ->andReturnSelf();
    
    Log::shouldReceive('log')
        ->once()
        ->with('info', 'Message', ['key' => 'value']);
    
    $logger = new DeferredLogger();
    $logger->defer('test', 'info', 'Message', ['key' => 'value']);
    
    $logger->flush();
    
    expect($logger->count())->toBe(0);
});

test('deferred logger handles individual write errors in batch fallback', function () {
    $mockBatchableHandler = \Mockery::mock(
        \Ermetix\LaravelLogger\Logging\Contracts\BatchableHandler::class,
        \Monolog\Handler\HandlerInterface::class
    );
    
    $mockLogger = \Mockery::mock(\Monolog\Logger::class);
    $mockLogger->shouldReceive('getHandlers')
        ->andReturn([$mockBatchableHandler]);
    
    Log::shouldReceive('channel')
        ->once()
        ->with('opensearch')
        ->andReturn($mockLogger);
    
    // Batch fails
    $mockBatchableHandler->shouldReceive('writeBatch')
        ->once()
        ->andThrow(new \Exception('Batch failed'));
    
    // First individual write succeeds, second fails
    $mockBatchableHandler->shouldReceive('handle')
        ->once()
        ->andReturn();
    
    $mockBatchableHandler->shouldReceive('handle')
        ->once()
        ->andThrow(new \Exception('Individual write failed'));
    
    $logger = new DeferredLogger();
    $logger->defer('opensearch', 'info', 'Message 1', ['log_index' => 'api_log']);
    $logger->defer('opensearch', 'error', 'Message 2', ['log_index' => 'general_log']);
    
    // Should not throw even if individual writes fail
    $logger->flush();
    
    expect($logger->count())->toBe(0);
});

test('deferred logger handles non-batchable handler errors', function () {
    $mockBatchableHandler = \Mockery::mock(
        \Ermetix\LaravelLogger\Logging\Contracts\BatchableHandler::class,
        \Monolog\Handler\HandlerInterface::class
    );
    
    $mockNonBatchableHandler = \Mockery::mock(\Monolog\Handler\HandlerInterface::class);
    
    $mockLogger = \Mockery::mock(\Monolog\Logger::class);
    $mockLogger->shouldReceive('getHandlers')
        ->andReturn([$mockBatchableHandler, $mockNonBatchableHandler]);
    
    Log::shouldReceive('channel')
        ->once()
        ->with('opensearch')
        ->andReturn($mockLogger);
    
    $mockBatchableHandler->shouldReceive('writeBatch')
        ->once();
    
    // Non-batchable handler throws on first record, succeeds on second
    $mockNonBatchableHandler->shouldReceive('handle')
        ->once()
        ->andThrow(new \Exception('Handler error'));
    
    $mockNonBatchableHandler->shouldReceive('handle')
        ->once()
        ->andReturn();
    
    $logger = new DeferredLogger();
    $logger->defer('opensearch', 'info', 'Message 1', ['log_index' => 'api_log']);
    $logger->defer('opensearch', 'error', 'Message 2', ['log_index' => 'general_log']);
    
    // Should not throw even if non-batchable handler fails
    $logger->flush();
    
    expect($logger->count())->toBe(0);
});

test('deferred logger handles fallback logging errors', function () {
    Log::shouldReceive('channel')
        ->once()
        ->with('test')
        ->andThrow(new \Exception('Channel error'));
    
    // Fallback attempts (one per log in the catch block)
    Log::shouldReceive('channel')
        ->twice()
        ->with('test')
        ->andReturnSelf();
    
    Log::shouldReceive('log')
        ->once()
        ->with('info', 'Message 1', ['key1' => 'value1']);
    
    Log::shouldReceive('log')
        ->once()
        ->with('error', 'Message 2', ['key2' => 'value2']);
    
    $logger = new DeferredLogger();
    $logger->defer('test', 'info', 'Message 1', ['key1' => 'value1']);
    $logger->defer('test', 'error', 'Message 2', ['key2' => 'value2']);
    
    // Should not throw even if some fallback attempts fail
    $logger->flush();
    
    expect($logger->count())->toBe(0);
});

test('deferred logger processes non-batchable handlers when batchable handlers exist', function () {
    $mockBatchableHandler = \Mockery::mock(
        \Ermetix\LaravelLogger\Logging\Contracts\BatchableHandler::class,
        \Monolog\Handler\HandlerInterface::class
    );
    
    $mockNonBatchableHandler = \Mockery::mock(\Monolog\Handler\HandlerInterface::class);
    
    $mockLogger = \Mockery::mock(\Monolog\Logger::class);
    $mockLogger->shouldReceive('getHandlers')
        ->twice() // Once for getBatchableHandlers, once for getAllHandlers
        ->andReturn([$mockBatchableHandler, $mockNonBatchableHandler]);
    
    Log::shouldReceive('channel')
        ->once()
        ->with('opensearch')
        ->andReturn($mockLogger);
    
    $mockBatchableHandler->shouldReceive('writeBatch')
        ->once();
    
    $mockNonBatchableHandler->shouldReceive('handle')
        ->once();
    
    $logger = new DeferredLogger();
    $logger->defer('opensearch', 'info', 'Message', ['log_index' => 'api_log']);
    
    $logger->flush();
    
    expect($logger->count())->toBe(0);
});

test('deferred logger handles errors in fallback logging catch block', function () {
    Log::shouldReceive('channel')
        ->once()
        ->with('test')
        ->andThrow(new \Exception('Channel error'));
    
    // Fallback attempts (one per log in the catch block)
    // First log: channel succeeds, log succeeds
    Log::shouldReceive('channel')
        ->once()
        ->with('test')
        ->andReturnSelf();
    
    Log::shouldReceive('log')
        ->once()
        ->with('info', 'Message 1', ['key1' => 'value1']);
    
    // Second log: channel succeeds but log fails (covers inner catch)
    Log::shouldReceive('channel')
        ->once()
        ->with('test')
        ->andReturnSelf();
    
    Log::shouldReceive('log')
        ->once()
        ->with('error', 'Message 2', ['key2' => 'value2'])
        ->andThrow(new \Exception('Log error'));
    
    $logger = new DeferredLogger();
    $logger->defer('test', 'info', 'Message 1', ['key1' => 'value1']);
    $logger->defer('test', 'error', 'Message 2', ['key2' => 'value2']);
    
    // Should not throw even if some fallback attempts fail
    $logger->flush();
    
    expect($logger->count())->toBe(0);
});

test('deferred logger auto-flushes when max limit is reached', function () {
    $mockLogger = \Mockery::mock(\Psr\Log\LoggerInterface::class);
    
    Log::shouldReceive('channel')
        ->once()
        ->with('single')
        ->andReturn($mockLogger);
    
    $mockLogger->shouldReceive('log')
        ->times(5)
        ->with(\Mockery::any(), \Mockery::any(), \Mockery::any());
    
    // Create logger with max limit of 5
    $logger = new DeferredLogger(maxLogs: 5, warnOnLimit: false);
    
    // Add 5 logs - should trigger auto-flush on the 5th
    for ($i = 1; $i <= 5; $i++) {
        $logger->defer('single', 'info', "Message $i", ['key' => "value$i"]);
    }
    
    // After auto-flush, count should be 0
    expect($logger->count())->toBe(0);
    expect($logger->getAutoFlushCount())->toBe(1);
});

test('deferred logger continues normally after auto-flush', function () {
    $mockLogger = \Mockery::mock(\Psr\Log\LoggerInterface::class);
    
    Log::shouldReceive('channel')
        ->twice() // Once for auto-flush, once for manual flush
        ->with('single')
        ->andReturn($mockLogger);
    
    // First 5 logs trigger auto-flush
    $mockLogger->shouldReceive('log')
        ->times(5)
        ->with(\Mockery::any(), \Mockery::any(), \Mockery::any());
    
    // Next 3 logs are accumulated
    // Then manual flush processes them
    $mockLogger->shouldReceive('log')
        ->times(3)
        ->with(\Mockery::any(), \Mockery::any(), \Mockery::any());
    
    $logger = new DeferredLogger(maxLogs: 5, warnOnLimit: false);
    
    // Add 5 logs - triggers auto-flush
    for ($i = 1; $i <= 5; $i++) {
        $logger->defer('single', 'info', "Message $i", ['key' => "value$i"]);
    }
    
    expect($logger->count())->toBe(0);
    expect($logger->getAutoFlushCount())->toBe(1);
    
    // Add 3 more logs - should accumulate normally
    for ($i = 6; $i <= 8; $i++) {
        $logger->defer('single', 'info', "Message $i", ['key' => "value$i"]);
    }
    
    expect($logger->count())->toBe(3);
    
    // Manual flush at the end
    $logger->flush();
    
    expect($logger->count())->toBe(0);
});

test('deferred logger can trigger multiple auto-flushes', function () {
    $mockLogger = \Mockery::mock(\Psr\Log\LoggerInterface::class);
    
    Log::shouldReceive('channel')
        ->times(3) // Three auto-flushes
        ->with('single')
        ->andReturn($mockLogger);
    
    // Each auto-flush processes 5 logs
    $mockLogger->shouldReceive('log')
        ->times(15) // 3 auto-flushes * 5 logs each
        ->with(\Mockery::any(), \Mockery::any(), \Mockery::any());
    
    $logger = new DeferredLogger(maxLogs: 5, warnOnLimit: false);
    
    // Add 15 logs - should trigger 3 auto-flushes
    for ($i = 1; $i <= 15; $i++) {
        $logger->defer('single', 'info', "Message $i", ['key' => "value$i"]);
    }
    
    expect($logger->count())->toBe(0);
    expect($logger->getAutoFlushCount())->toBe(3);
});

test('deferred logger logs warning when limit is reached and warn enabled', function () {
    $mockLogger = \Mockery::mock(\Psr\Log\LoggerInterface::class);
    
    // Mock channel() to return the same logger instance for both calls
    // (one for warning, one for actual logs)
    Log::shouldReceive('channel')
        ->twice()
        ->with('single')
        ->andReturn($mockLogger);
    
    // Should log a warning first, then the 5 actual logs
    $mockLogger->shouldReceive('warning')
        ->once()
        ->with(
            'DeferredLogger: Maximum log limit reached, auto-flushing',
            \Mockery::on(function ($context) {
                return isset($context['limit'])
                    && $context['limit'] === 5
                    && isset($context['logs_flushed'])
                    && $context['logs_flushed'] === 5
                    && isset($context['auto_flush_count'])
                    && $context['auto_flush_count'] === 1;
            })
        );
    
    $mockLogger->shouldReceive('log')
        ->times(5)
        ->with(\Mockery::any(), \Mockery::any(), \Mockery::any());
    
    $logger = new DeferredLogger(maxLogs: 5, warnOnLimit: true);
    
    // Add 5 logs - should trigger auto-flush with warning
    for ($i = 1; $i <= 5; $i++) {
        $logger->defer('single', 'info', "Message $i", ['key' => "value$i"]);
    }
    
    expect($logger->getAutoFlushCount())->toBe(1);
});

test('deferred logger does not log warning when warn disabled', function () {
    $mockLogger = \Mockery::mock(\Psr\Log\LoggerInterface::class);
    
    Log::shouldReceive('channel')
        ->once()
        ->with('single')
        ->andReturn($mockLogger);
    
    $mockLogger->shouldReceive('log')
        ->times(5)
        ->with(\Mockery::any(), \Mockery::any(), \Mockery::any());
    
    // Should NOT log a warning
    Log::shouldReceive('warning')->never();
    
    $logger = new DeferredLogger(maxLogs: 5, warnOnLimit: false);
    
    // Add 5 logs - should trigger auto-flush without warning
    for ($i = 1; $i <= 5; $i++) {
        $logger->defer('single', 'info', "Message $i", ['key' => "value$i"]);
    }
    
    expect($logger->getAutoFlushCount())->toBe(1);
});

test('deferred logger with null limit does not auto-flush', function () {
    $logger = new DeferredLogger(maxLogs: null, warnOnLimit: false);
    
    // Add many logs - should never auto-flush
    for ($i = 1; $i <= 100; $i++) {
        $logger->defer('single', 'info', "Message $i", ['key' => "value$i"]);
    }
    
    expect($logger->count())->toBe(100);
    expect($logger->getAutoFlushCount())->toBe(0);
    expect($logger->getMaxLogs())->toBeNull();
});

test('deferred logger with zero limit does not auto-flush', function () {
    $logger = new DeferredLogger(maxLogs: 0, warnOnLimit: false);
    
    // Add many logs - should never auto-flush (0 is treated as disabled)
    for ($i = 1; $i <= 100; $i++) {
        $logger->defer('single', 'info', "Message $i", ['key' => "value$i"]);
    }
    
    expect($logger->count())->toBe(100);
    expect($logger->getAutoFlushCount())->toBe(0);
    expect($logger->getMaxLogs())->toBe(0);
});

test('deferred logger autoFlush handles warning logging exceptions gracefully', function () {
    $mockLogger = \Mockery::mock(\Psr\Log\LoggerInterface::class);
    
    // First call (for warning) throws, second call (for actual logs) succeeds
    Log::shouldReceive('channel')
        ->once()
        ->with('single')
        ->andThrow(new \RuntimeException('Log service unavailable'));
    
    // Should still flush logs even if warning fails
    Log::shouldReceive('channel')
        ->once()
        ->with('single')
        ->andReturn($mockLogger);
    
    $mockLogger->shouldReceive('log')
        ->times(5)
        ->with(\Mockery::any(), \Mockery::any(), \Mockery::any());
    
    $logger = new DeferredLogger(maxLogs: 5, warnOnLimit: true);
    
    // Add 5 logs - should trigger auto-flush, warning fails but flush continues
    for ($i = 1; $i <= 5; $i++) {
        $logger->defer('single', 'info', "Message $i", ['key' => "value$i"]);
    }
    
    expect($logger->getAutoFlushCount())->toBe(1);
    expect($logger->count())->toBe(0); // Should be flushed
});

test('deferred logger clear resets auto-flush count', function () {
    $mockLogger = \Mockery::mock(\Psr\Log\LoggerInterface::class);
    
    Log::shouldReceive('channel')
        ->once()
        ->with('single')
        ->andReturn($mockLogger);
    
    $mockLogger->shouldReceive('log')
        ->times(5)
        ->with(\Mockery::any(), \Mockery::any(), \Mockery::any());
    
    $logger = new DeferredLogger(maxLogs: 5, warnOnLimit: false);
    
    // Trigger auto-flush
    for ($i = 1; $i <= 5; $i++) {
        $logger->defer('single', 'info', "Message $i", ['key' => "value$i"]);
    }
    
    expect($logger->getAutoFlushCount())->toBe(1);
    
    // Clear should reset the counter
    $logger->clear();
    
    expect($logger->getAutoFlushCount())->toBe(0);
    expect($logger->count())->toBe(0);
});
