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
