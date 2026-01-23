<?php

namespace Ermetix\LaravelLogger\Logging\Builders;

use Ermetix\LaravelLogger\Logging\Contracts\OpenSearchDocumentBuilder;
use Monolog\LogRecord;

class DefaultOpenSearchDocumentBuilder implements OpenSearchDocumentBuilder
{
    public function index(LogRecord $record): string
    {
        // Prefer explicit routing from context/extra; fall back to "general_log".
        $index = $record->context['log_index'] ?? ($record->extra['log_index'] ?? null);

        if (is_string($index) && $index !== '') {
            return $index;
        }

        return 'general_log';
    }

    public function document(LogRecord $record): array
    {
        // OpenSearch common conventions: @timestamp + ECS-ish fields.
        // Note: 'channel' field removed as it doesn't add value (always "opensearch").
        // All specific fields from context are placed directly in the document (not inside a 'context' object).
        
        // message field removed - it's redundant as we have structured fields for each log type
        // We keep it in Monolog record for compatibility but don't store it in OpenSearch
        $doc = [
            '@timestamp' => $record->datetime->format(DATE_ATOM),
            'level' => strtolower($record->level->getName()),
            // Convenience shortcut for correlation. request_id is also available in extra.request_id.
            'request_id' => $record->extra['request_id'] ?? ($record->context['request_id'] ?? null),
        ];

        // Add all context fields directly to the document (except log_index which is only for routing)
        foreach ($record->context as $key => $value) {
            // Do not overwrite fields we already set (e.g. request_id from extra)
            if ($key !== 'log_index' && !array_key_exists($key, $doc)) {
                $doc[$key] = $value;
            }
        }

        // Add extra fields if needed (currently only request_id is used)
        if (isset($record->extra['request_id'])) {
            // Already added above
        }

        // Auto-populate common fields if not already present
        $this->populateCommonFields($doc, $record);

        return $doc;
    }

    /**
     * Populate common fields automatically if not already present in the document.
     */
    protected function populateCommonFields(array &$doc, LogRecord $record): void
    {
        // Environment (from config)
        if (!isset($doc['environment']) && function_exists('config')) {
            $env = config('app.env');
            if ($env !== null) {
                $doc['environment'] = $env;
            }
        }

        // Hostname
        if (!isset($doc['hostname'])) {
            $hostname = gethostname();
            if ($hostname !== false) {
                $doc['hostname'] = $hostname;
            }
        }

        // Service name (from config, fallback to app.name)
        if (!isset($doc['service_name']) && function_exists('config')) {
            $serviceName = config('laravel-logger.service_name') 
                        ?? config('app.name') 
                        ?? null;
            if ($serviceName !== null) {
                $doc['service_name'] = $serviceName;
            }
        }

        // App version (from config, if available)
        if (!isset($doc['app_version']) && function_exists('config')) {
            $version = config('app.version');
            if ($version !== null) {
                $doc['app_version'] = $version;
            }
        }

        // Session ID (from Laravel session, if available)
        if (!isset($doc['session_id']) && function_exists('session')) {
            try {
                $sessionId = session()->getId();
                if ($sessionId !== null && $sessionId !== '') {
                    $doc['session_id'] = $sessionId;
                }
            } catch (\Throwable $e) {
                // Session not available, ignore
            }
        }

        // Source location (file, line, class, function) from backtrace if not already present
        // Skip for api_log, orm_log, integration_log, cron_log, error_log as these fields are not useful:
        // - api_log: would always point to middleware
        // - orm_log: would always point to LogDatabaseQuery listener
        // - integration_log: would always point to Log::integration() call
        // - cron_log: would always point to Log::cron() call
        // - error_log: stack_trace already contains all location info for every frame
        // Only include for general_log (debug)
        $index = $this->index($record);
        $skipSourceLocation = in_array($index, ['api_log', 'orm_log', 'integration_log', 'cron_log', 'error_log'], true);
        if (!$skipSourceLocation && (!isset($doc['file']) || !isset($doc['line'])) && function_exists('debug_backtrace')) {
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
            
            // Skip Monolog frames and find the actual caller
            foreach ($backtrace as $frame) {
                $file = $frame['file'] ?? null;
                $class = $frame['class'] ?? null;
                
                // Skip Monolog and Laravel Logger internal frames
                $skip = false;
                if ($file !== null) {
                    $normalizedFile = str_replace('\\', '/', $file);
                    $skip = str_contains($normalizedFile, 'vendor/monolog')
                        || str_contains($normalizedFile, 'Ermetix/LaravelLogger')
                        || str_contains($normalizedFile, 'Illuminate/Log');
                }

                if ($skip) {
                    // Skip internal frames
                } elseif ($file !== null) {
                    // Found a non-Monolog frame
                    if (!isset($doc['file'])) {
                        $doc['file'] = $file;
                    }
                    if (!isset($doc['line'])) {
                        $doc['line'] = $frame['line'] ?? null;
                    }
                    if (!isset($doc['class']) && $class !== null) {
                        $doc['class'] = $class;
                    }
                    if (!isset($doc['function']) && isset($frame['function'])) {
                        $doc['function'] = $frame['function'];
                    }
                    break;
                }
            }
        }
    }
}
