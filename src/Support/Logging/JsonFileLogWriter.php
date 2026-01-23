<?php

namespace Ermetix\LaravelLogger\Support\Logging;

use Ermetix\LaravelLogger\Support\Logging\Contracts\LogObject;
use Illuminate\Support\Str;

/**
 * Writes LogObject data to JSON Lines files (one JSON object per line).
 *
 * Files are routed by $object->index() (e.g. general_log, api_log, orm_log...).
 */
class JsonFileLogWriter
{
    /**
     * Build the target file path for the given object.
     */
    public function pathFor(LogObject $object): string
    {
        $dir = storage_path('logs/laravel-logger');
        $dir = rtrim($dir, DIRECTORY_SEPARATOR);

        $index = $this->sanitizeIndex($object->index());
        $date = $this->todayDateString(); // fixed daily rotation
        $filename = $index.'-'.$date.'.jsonl';

        return $dir.DIRECTORY_SEPARATOR.$filename;
    }

    /**
     * Build the JSON-serializable record for the given object.
     *
     * @return array<string, mixed>
     */
    public function recordFor(LogObject $object): array
    {
        // We store a stable timestamp field for ingestion tools.
        // (ISO 8601 with microseconds if available)
        $timestamp = (new \DateTimeImmutable('now'))->format('Y-m-d\TH:i:s.uP');

        return [
            '@timestamp' => $timestamp,
            'log_index' => $object->index(),
            ...$object->toArray(),
        ];
    }

    /**
     * Append the log record to the routed file.
     *
     * Never throws (we don't want logging to break the app).
     */
    public function write(LogObject $object): void
    {
        try {
            $path = $this->pathFor($object);
            $dir = dirname($path);

            if (!is_dir($dir)) {
                // Use secure permissions (0755) instead of 0777 to prevent security issues
                // Owner: read/write/execute, Group: read/execute, Others: read/execute
                @mkdir($dir, 0755, true);
            }

            $this->maybePrune($dir);

            $record = $this->recordFor($object);
            $json = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            if (!is_string($json)) {
                $json = json_encode([
                    '@timestamp' => (new \DateTimeImmutable('now'))->format('c'),
                    'log_index' => $object->index(),
                    'message' => $object->message(),
                    'level' => $object->level(),
                    'json_error' => json_last_error_msg(),
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }

            if (!is_string($json)) {
                return;
            }

            @file_put_contents($path, $json.PHP_EOL, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
            // Intentionally ignore any errors while writing log files.
        }
    }

    private function todayDateString(): string
    {
        return (new \DateTimeImmutable('now'))->format('Y-m-d');
    }

    private function maybePrune(string $dir): void
    {
        $retentionDays = (int) config('laravel-logger.index_file.retention_days', 0);
        if ($retentionDays <= 0) {
            return;
        }

        // Throttle pruning to once per day per directory.
        // Use file locking to prevent race conditions in multi-worker environments.
        $marker = rtrim($dir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'.laravel-logger-json-last-prune';
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
            $base = basename($file);
            $fileDate = $this->dateFromFilename($base);
            if ($fileDate === null) {
                $mtime = @filemtime($file);
                if (is_int($mtime)) {
                    $fileDate = (new \DateTimeImmutable())->setTimestamp($mtime)->setTime(0, 0, 0);
                }
            }

            if ($fileDate !== null && $fileDate < $keepFrom) {
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
            // Handle malformed dates like "2026-13-45" (invalid month/day)
            // The regex ensures format but not validity
            return null;
        }
    }

    private function sanitizeIndex(string $index): string
    {
        $index = trim($index);
        $index = $index !== '' ? $index : 'log';

        // Keep a safe filename base: letters, numbers, dash, underscore, dot.
        $index = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $index) ?? 'log';

        return Str::lower($index);
    }
}

