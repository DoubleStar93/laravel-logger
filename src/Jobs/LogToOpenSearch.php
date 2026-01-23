<?php

namespace Ermetix\LaravelLogger\Jobs;

use Ermetix\LaravelLogger\Logging\Builders\DefaultOpenSearchDocumentBuilder;
use Ermetix\LaravelLogger\Logging\Handlers\OpenSearchIndexHandler;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Monolog\Level;
use Monolog\LogRecord;

/**
 * Queue job for logging to OpenSearch asynchronously.
 * 
 * Note: The package uses deferred logging by default (in-memory).
 * This job is provided as an alternative for queue-based logging if needed.
 */
class LogToOpenSearch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private readonly string $index,
        private readonly array $document,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $opensearchUrl = config('logging.channels.opensearch.url', 'http://localhost:9200');
        $builder = app(DefaultOpenSearchDocumentBuilder::class);
        
        // Create a LogRecord from the document data
        $level = Level::fromName($this->document['level'] ?? 'info');
        $datetime = isset($this->document['@timestamp']) 
            ? new \DateTimeImmutable($this->document['@timestamp']) 
            : new \DateTimeImmutable();
        
        $record = new LogRecord(
            datetime: $datetime,
            channel: 'opensearch',
            level: $level,
            message: $this->document['message'] ?? '',
            context: $this->document,
        );
        
        /** @var OpenSearchIndexHandler $handler */
        $handler = app()->make(OpenSearchIndexHandler::class, [
            'baseUrl' => $opensearchUrl,
            'index' => $this->index,
            'documentBuilder' => $builder,
            'username' => config('logging.channels.opensearch.username'),
            'password' => config('logging.channels.opensearch.password'),
            'level' => $this->document['level'] ?? 'info',
            'timeout' => config('logging.channels.opensearch.timeout', 2),
            'silent' => config('logging.channels.opensearch.silent', true),
            'verifyTls' => config('logging.channels.opensearch.verify_tls', true),
        ]);
        
        $handler->handle($record);
    }
}
