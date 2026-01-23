<?php

namespace Ermetix\LaravelLogger\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Queue job for logging to Kafka via REST Proxy.
 * 
 * Note: The package uses deferred logging by default (in-memory).
 * This job is provided as an alternative for queue-based logging if needed.
 */
class LogToKafka implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        private readonly string $message = 'kafka_test',
        private readonly array $context = [],
    ) {}

    public function handle(): void
    {
        $context = $this->context + [
            // Used by IndexKeyKafkaValueBuilder (if configured)
            'log_index' => 'general_log',

            // Extra metadata to make the message easy to spot
            'event_id' => (string) Str::uuid(),
            'sent_at' => now()->toAtomString(),
            'app_env' => config('app.env'),
        ];

        Log::channel('kafka')->info($this->message, $context);
    }
}
