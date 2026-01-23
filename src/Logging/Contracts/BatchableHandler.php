<?php

namespace Ermetix\LaravelLogger\Logging\Contracts;

use Monolog\LogRecord;

/**
 * Interface for Monolog handlers that support batch processing.
 * 
 * Handlers implementing this interface can process multiple log records
 * in a single operation, improving performance when flushing deferred logs.
 */
interface BatchableHandler
{
    /**
     * Write multiple log records in a single batch operation.
     * 
     * @param array<int, LogRecord> $records Array of log records to write
     * @return void
     */
    public function writeBatch(array $records): void;
}
