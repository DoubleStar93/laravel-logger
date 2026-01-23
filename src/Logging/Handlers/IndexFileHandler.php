<?php

namespace Ermetix\LaravelLogger\Logging\Handlers;

use Ermetix\LaravelLogger\Logging\Contracts\BatchableHandler;
use Ermetix\LaravelLogger\Support\Logging\LevelNormalizer;
use Illuminate\Support\Str;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

/**
 * Writes JSONL (one JSON object per line) to:
 *   storage/logs/laravel-logger/<log_index>-YYYY-MM-DD.jsonl
 *
 * This handler is intended to be used by the Laravel logging channel "index_file".
 */
class IndexFileHandler extends AbstractProcessingHandler implements BatchableHandler
{
    public function __construct(
        string|Level $level = 'debug',
        bool $bubble = true,
    ) {
        parent::__construct(LevelNormalizer::normalize($level), $bubble);
    }

    protected function write(LogRecord $record): void
    {
        try {
            $index = $record->context['log_index'] ?? 'general_log';
            if (!is_string($index) || $index === '') {
                $index = 'general_log';
            }

            $dir = rtrim(storage_path('logs/laravel-logger'), DIRECTORY_SEPARATOR);
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }

            $this->maybePrune($dir);

            $date = $record->datetime->format('Y-m-d');
            $safeIndex = $this->sanitizeIndex($index);
            $path = $dir.DIRECTORY_SEPARATOR.$safeIndex.'-'.$date.'.jsonl';

            $doc = $record->context;
            unset($doc['log_index']);

            // Ensure a stable timestamp + index inside the record.
            $payload = [
                '@timestamp' => $record->datetime->format(DATE_ATOM),
                'log_index' => $index,
                ...$doc,
            ];

            // If someone logs directly to the channel without putting message in context,
            // ensure it's present.
            if (!array_key_exists('message', $payload)) {
                $payload['message'] = $record->message;
            }

            $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (!is_string($json)) {
                return;
            }

            @file_put_contents($path, $json.PHP_EOL, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
            // Never break the app because of file logging.
        }
    }

    private function maybePrune(string $dir): void
    {
        $retentionDays = (int) config('laravel-logger.index_file.retention_days', 0);
        if ($retentionDays <= 0) {
            return;
        }

        // Throttle pruning to once per day per directory.
        // Use file locking to prevent race conditions in multi-worker environments.
        $marker = rtrim($dir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'.laravel-logger-index-file-last-prune';
        $lockFile = $marker . '.lock';
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');

        // Quick check without lock first (optimization)
        $last = @file_get_contents($marker);
        if (is_string($last) && trim($last) === $today) {
            return;
        }

        // Acquire exclusive lock to prevent concurrent pruning by multiple workers
        $fp = @fopen($lockFile, 'c+');
        if (!$fp) {
            // Cannot acquire lock file, skip pruning to avoid errors
            return;
        }

        // Try to acquire exclusive non-blocking lock
        if (flock($fp, LOCK_EX | LOCK_NB)) {
            try {
                // Double-check pattern: verify again after acquiring lock
                // Another process might have already done the pruning while we were waiting
                $last = @file_get_contents($marker);
                if (!is_string($last) || trim($last) !== $today) {
                    $this->pruneDirectory($dir, $retentionDays);
                    @file_put_contents($marker, $today);
                }
            } finally {
                // Always release the lock
                flock($fp, LOCK_UN);
            }
        }
        
        @fclose($fp);
    }

    private function pruneDirectory(string $dir, int $retentionDays): void
    {
        $dir = rtrim($dir, DIRECTORY_SEPARATOR);

        // Keep today and previous (N-1) days.
        $keepFrom = (new \DateTimeImmutable('today'))->sub(new \DateInterval('P'.max(0, $retentionDays - 1).'D'));

        $files = @glob($dir.DIRECTORY_SEPARATOR.'*.jsonl') ?: [];
        foreach ($files as $file) {
            $fileDate = $this->dateFromFilename(basename($file));
            if ($fileDate === null) {
                continue;
            }

            if ($fileDate < $keepFrom) {
                @unlink($file);
            }
        }
    }

    private function dateFromFilename(string $filename): ?\DateTimeImmutable
    {
        // Look for YYYY-MM-DD anywhere in the filename.
        if (!preg_match('/\b(\d{4}-\d{2}-\d{2})\b/', $filename, $m)) {
            return null;
        }

        try {
            return new \DateTimeImmutable($m[1]);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function sanitizeIndex(string $index): string
    {
        $index = trim($index);
        $index = $index !== '' ? $index : 'log';

        $index = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $index) ?? 'log';

        return Str::lower($index);
    }

    /**
     * Write multiple log records in a single batch to JSONL files.
     * Groups records by index and date, writing all lines for each file at once.
     * 
     * @param array<int, LogRecord> $records
     */
    public function writeBatch(array $records): void
    {
        if (empty($records)) {
            return;
        }

        try {
            $dir = rtrim(storage_path('logs/laravel-logger'), DIRECTORY_SEPARATOR);
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }

            $this->maybePrune($dir);

            // Group records by file path (index + date)
            $fileGroups = [];
            foreach ($records as $record) {
                $index = $record->context['log_index'] ?? 'general_log';
                if (!is_string($index) || $index === '') {
                    $index = 'general_log';
                }

                $date = $record->datetime->format('Y-m-d');
                $safeIndex = $this->sanitizeIndex($index);
                $path = $dir.DIRECTORY_SEPARATOR.$safeIndex.'-'.$date.'.jsonl';

                if (!isset($fileGroups[$path])) {
                    $fileGroups[$path] = [];
                }

                $fileGroups[$path][] = $record;
            }

            // Write all records for each file in a single operation
            foreach ($fileGroups as $path => $groupRecords) {
                $this->writeBatchToFile($path, $groupRecords);
            }
        } catch (\Throwable $e) {
            // Never break the app because of file logging.
        }
    }

    /**
     * Write a batch of records to a single file.
     * 
     * @param string $path
     * @param array<int, LogRecord> $records
     */
    private function writeBatchToFile(string $path, array $records): void
    {
        $lines = [];
        foreach ($records as $record) {
            $index = $record->context['log_index'] ?? 'general_log';
            if (!is_string($index) || $index === '') {
                $index = 'general_log';
            }

            $doc = $record->context;
            unset($doc['log_index']);

            $payload = [
                '@timestamp' => $record->datetime->format(DATE_ATOM),
                'log_index' => $index,
                ...$doc,
            ];

            if (!array_key_exists('message', $payload)) {
                $payload['message'] = $record->message;
            }

            $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (is_string($json)) {
                $lines[] = $json;
            }
        }

        if (!empty($lines)) {
            @file_put_contents($path, implode(PHP_EOL, $lines).PHP_EOL, FILE_APPEND | LOCK_EX);
        }
    }
}

