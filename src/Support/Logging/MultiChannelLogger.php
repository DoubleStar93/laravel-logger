<?php

namespace Ermetix\LaravelLogger\Support\Logging;

use Ermetix\LaravelLogger\Support\Logging\Contracts\LogObject;
use Illuminate\Support\Facades\Log;

class MultiChannelLogger
{
    public function __construct(
        private readonly ?DeferredLogger $deferredLogger = null,
    ) {}

    /**
     * Log a LogObject to all channels configured in the "stack" channel.
     * If stack is not configured, falls back to the default channel.
     * 
     * @param bool $defer If true, logs are accumulated in memory and written at the end of the request/job.
     *                    If false, logs are written immediately (sync, default).
     */
    public function log(LogObject $object, bool $defer = false): void
    {
        $message = $object->message();

        $context = [
            // Used by the OpenSearch document builder to route the index.
            'log_index' => $object->index(),
            // All specific fields from the LogObject (will be placed directly in document, not in context)
            ...$object->toArray(),
        ];

        // Ensure request_id and trace_id are always present in context
        // They will be automatically added to extra by ContextLogProcessor
        $this->ensureCorrelationIds($context);

        foreach ($this->channels() as $channel) {
            if ($defer && $this->deferredLogger !== null) {
                // Defer logging to end of request/job
                $this->deferredLogger->defer($channel, $object->level(), $message, $context);
            } else {
                // Sync logging (immediate)
                Log::channel($channel)->log($object->level(), $message, $context);
            }
        }
    }

    /**
     * Ensure request_id and trace_id are always present in context.
     * If not present, generate them to ensure they're never empty.
     */
    private function ensureCorrelationIds(array &$context): void
    {
        // Check if request_id is already in context (from LogObject or Context facade)
        if (empty($context['request_id'])) {
            // Try to get from Laravel Context facade
            if (function_exists('\\Illuminate\\Support\\Facades\\Context::get')) {
                $requestId = \Illuminate\Support\Facades\Context::get('request_id');
                if (!empty($requestId)) {
                    $context['request_id'] = $requestId;
                }
            }
            
            // If still empty, generate one
            if (empty($context['request_id'])) {
                $context['request_id'] = (string) \Illuminate\Support\Str::uuid();
            }
        }

        // Check if trace_id is already in context (from LogObject or Context facade)
        if (empty($context['trace_id'])) {
            // Try to get from Laravel Context facade
            if (function_exists('\\Illuminate\\Support\\Facades\\Context::get')) {
                $traceId = \Illuminate\Support\Facades\Context::get('trace_id');
                if (!empty($traceId)) {
                    $context['trace_id'] = $traceId;
                }
            }
            
            // If still empty, use request_id to keep them linked
            if (empty($context['trace_id'])) {
                $context['trace_id'] = $context['request_id'];
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function channels(): array
    {
        $stack = config('logging.channels.stack.channels');

        if (is_array($stack) && count($stack) > 0) {
            return array_values(array_filter($stack, fn ($c) => is_string($c) && $c !== ''));
        }

        $default = config('logging.default', 'stack');

        if (is_string($default) && $default !== '') {
            return [$default];
        }

        return ['stack'];
    }
}
