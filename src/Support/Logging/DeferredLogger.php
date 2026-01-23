<?php

namespace Ermetix\LaravelLogger\Support\Logging;

use Ermetix\LaravelLogger\Logging\Contracts\BatchableHandler;
use Ermetix\LaravelLogger\Support\Logging\Contracts\LogObject;
use Monolog\Level;
use Monolog\LogRecord;

/**
 * Accumulates logs in memory and writes them all at once at the end of the request/job.
 * This avoids blocking the execution and doesn't require external queue systems.
 * 
 * Supports batch processing for handlers that implement BatchableHandler interface,
 * reducing the number of HTTP requests and file operations.
 * 
 * Automatically flushes logs when the configured maximum limit is reached to prevent
 * memory exhaustion, then continues execution normally.
 */
class DeferredLogger
{
    /**
     * @var array<int, array{channel: string, level: string, message: string, context: array, datetime: \DateTimeImmutable}>
     */
    private array $logs = [];

    /**
     * Maximum number of logs to accumulate before auto-flushing.
     * Set to 0 or null to disable the limit.
     */
    private readonly ?int $maxLogs;

    /**
     * Whether to log a warning when the limit is reached.
     */
    private readonly bool $warnOnLimit;

    /**
     * Number of times auto-flush has been triggered during this request/job.
     */
    private int $autoFlushCount = 0;

    public function __construct(?int $maxLogs = null, bool $warnOnLimit = true)
    {
        $this->maxLogs = $maxLogs;
        $this->warnOnLimit = $warnOnLimit;
    }

    /**
     * Add a log entry to the deferred queue.
     * Automatically flushes if the maximum limit is reached.
     */
    public function defer(string $channel, string $level, string $message, array $context): void
    {
        $this->logs[] = [
            'channel' => $channel,
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'datetime' => new \DateTimeImmutable('now'),
        ];

        // Check if we've reached the limit and auto-flush if needed
        if ($this->maxLogs !== null && $this->maxLogs > 0 && count($this->logs) >= $this->maxLogs) {
            $this->autoFlush();
        }
    }

    /**
     * Automatically flush logs when limit is reached.
     * This is called internally when the limit is reached, and execution continues normally.
     */
    private function autoFlush(): void
    {
        $this->autoFlushCount++;
        $logCount = count($this->logs);

        if ($this->warnOnLimit) {
            // Log a warning about the auto-flush
            // Use 'single' channel to avoid going through DeferredLogger again
            // This ensures the warning is written immediately to storage/logs/laravel.log
            try {
                \Illuminate\Support\Facades\Log::channel('single')->warning('DeferredLogger: Maximum log limit reached, auto-flushing', [
                    'limit' => $this->maxLogs,
                    'logs_flushed' => $logCount,
                    'auto_flush_count' => $this->autoFlushCount,
                ]);
            } catch (\Throwable $e) {
                // Silently fail to avoid breaking the application
            }
        }

        // Flush all accumulated logs
        $this->flush();
    }

    /**
     * Write all accumulated logs to their respective channels.
     * Uses batch processing when available to improve performance.
     */
    public function flush(): void
    {
        if (empty($this->logs)) {
            return;
        }

        // Group logs by channel
        $logsByChannel = [];
        foreach ($this->logs as $log) {
            $channel = $log['channel'];
            if (!isset($logsByChannel[$channel])) {
                $logsByChannel[$channel] = [];
            }
            $logsByChannel[$channel][] = $log;
        }

        // Process each channel
        foreach ($logsByChannel as $channel => $channelLogs) {
            $this->flushChannel($channel, $channelLogs);
        }

        $this->logs = [];
    }

    /**
     * Flush logs for a specific channel, using batch processing when available.
     * 
     * @param string $channel
     * @param array<int, array{channel: string, level: string, message: string, context: array}> $logs
     */
    private function flushChannel(string $channel, array $logs): void
    {
        try {
            $logger = \Illuminate\Support\Facades\Log::channel($channel);
            
            // Check if logger has batchable handlers
            $batchableHandlers = $this->getBatchableHandlers($logger);
            
            if (!empty($batchableHandlers)) {
                // Use batch processing
                $records = [];
                foreach ($logs as $log) {
                    $level = Level::fromName($log['level']);
                    $records[] = new LogRecord(
                        datetime: $log['datetime'],
                        channel: $channel,
                        level: $level,
                        message: $log['message'],
                        context: $log['context'],
                    );
                }

                // Call writeBatch on all batchable handlers
                foreach ($batchableHandlers as $handler) {
                    try {
                        $handler->writeBatch($records);
                    } catch (\Throwable $e) {
                        // If batch fails, fall back to individual writes
                        foreach ($records as $record) {
                            try {
                                $handler->handle($record);
                            } catch (\Throwable $e2) {
                                // Ignore individual handler errors
                            }
                        }
                    }
                }

                // Process remaining handlers (non-batchable) with individual records
                // Note: getAllHandlers() is only called when logger is Monolog\Logger (checked in getBatchableHandlers)
                $allHandlers = $logger->getHandlers();
                $nonBatchableHandlers = array_filter(
                    $allHandlers,
                    fn($h) => !($h instanceof BatchableHandler)
                );

                foreach ($nonBatchableHandlers as $handler) {
                    foreach ($records as $record) {
                        try {
                            $handler->handle($record);
                        } catch (\Throwable $e) {
                            // Ignore individual handler errors
                        }
                    }
                }
            } else {
                // No batchable handlers, use standard logging
                foreach ($logs as $log) {
                    $logger->log($log['level'], $log['message'], $log['context']);
                }
            }
        } catch (\Throwable $e) {
            // Fallback to standard logging if batch processing fails
            foreach ($logs as $log) {
                try {
                    \Illuminate\Support\Facades\Log::channel($channel)
                        ->log($log['level'], $log['message'], $log['context']);
                } catch (\Throwable $e2) {
                    // Ignore individual log errors
                }
            }
        }
    }

    /**
     * Get all batchable handlers from a Monolog logger.
     * 
     * @param \Psr\Log\LoggerInterface $logger
     * @return array<int, BatchableHandler>
     */
    private function getBatchableHandlers($logger): array
    {
        if (!($logger instanceof \Monolog\Logger)) {
            return [];
        }

        $handlers = [];
        foreach ($logger->getHandlers() as $handler) {
            if ($handler instanceof BatchableHandler) {
                $handlers[] = $handler;
            }
        }

        return $handlers;
    }

    /**
     * Get the number of deferred logs.
     */
    public function count(): int
    {
        return count($this->logs);
    }

    /**
     * Get the number of times auto-flush has been triggered.
     */
    public function getAutoFlushCount(): int
    {
        return $this->autoFlushCount;
    }

    /**
     * Get the configured maximum log limit.
     */
    public function getMaxLogs(): ?int
    {
        return $this->maxLogs;
    }

    /**
     * Clear all deferred logs without writing them.
     */
    public function clear(): void
    {
        $this->logs = [];
        $this->autoFlushCount = 0;
    }
}
