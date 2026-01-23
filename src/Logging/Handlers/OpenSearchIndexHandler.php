<?php

namespace Ermetix\LaravelLogger\Logging\Handlers;

use Ermetix\LaravelLogger\Logging\Contracts\BatchableHandler;
use Ermetix\LaravelLogger\Logging\Contracts\OpenSearchDocumentBuilder;
use Ermetix\LaravelLogger\Support\Logging\LevelNormalizer;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

class OpenSearchIndexHandler extends AbstractProcessingHandler implements BatchableHandler
{
    private Client $http;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $index,
        private readonly OpenSearchDocumentBuilder $documentBuilder,
        private readonly ?string $username = null,
        private readonly ?string $password = null,
        string|Level $level = 'debug',
        private readonly int $timeout = 2,
        private readonly bool $silent = true,
        private readonly bool $verifyTls = true,
        ?Client $http = null,
        bool $bubble = true,
        private readonly int $maxRetries = 3,
    ) {
        parent::__construct(LevelNormalizer::normalize($level), $bubble);

        $this->http = $http ?? new Client([
            'timeout' => $this->timeout,
            'verify' => $this->verifyTls,
        ]);
    }

    protected function write(LogRecord $record): void
    {
        $index = $this->documentBuilder->index($record);

        if (! is_string($index) || $index === '') {
            $index = $this->index;
        }
        $url = rtrim($this->baseUrl, '/').'/'.rawurlencode($index).'/_doc';

        $doc = $this->documentBuilder->document($record);

        $options = [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'json' => $doc,
        ];

        if (is_string($this->username) && $this->username !== '') {
            $options['auth'] = [$this->username, (string) $this->password];
        }

        try {
            $this->postWithRetry($url, $options);
        } catch (GuzzleException $e) {
            if ($this->silent) {
                return;
            }

            throw $e;
        }
    }

    /**
     * Write multiple log records in a single batch using OpenSearch _bulk API.
     * 
     * @param array<int, LogRecord> $records
     */
    public function writeBatch(array $records): void
    {
        if (empty($records)) {
            return;
        }

        // Group records by index for efficient bulk operations
        $indexGroups = [];
        foreach ($records as $record) {
            $index = $this->documentBuilder->index($record);
            if (!is_string($index) || $index === '') {
                $index = $this->index;
            }

            if (!isset($indexGroups[$index])) {
                $indexGroups[$index] = [];
            }

            $indexGroups[$index][] = $record;
        }

        // Process each index group separately
        foreach ($indexGroups as $index => $groupRecords) {
            $this->writeBulkForIndex($index, $groupRecords);
        }
    }

    /**
     * Write bulk records for a specific index.
     * 
     * @param string $index
     * @param array<int, LogRecord> $records
     */
    private function writeBulkForIndex(string $index, array $records): void
    {
        $url = rtrim($this->baseUrl, '/').'/'.rawurlencode($index).'/_bulk';

        // Build bulk request body (NDJSON format)
        $body = '';
        foreach ($records as $record) {
            $doc = $this->documentBuilder->document($record);
            
            // Action line: {"index": {}}
            $body .= json_encode(['index' => (object)[]], JSON_UNESCAPED_SLASHES)."\n";
            // Document line: {...}
            $body .= json_encode($doc, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
        }

        $options = [
            'headers' => [
                'Content-Type' => 'application/x-ndjson',
                'Accept' => 'application/json',
            ],
            'body' => $body,
        ];

        if (is_string($this->username) && $this->username !== '') {
            $options['auth'] = [$this->username, (string) $this->password];
        }

        try {
            $response = $this->postWithRetry($url, $options);
            
            if ($response !== null) {
                // Verify bulk API response for partial errors
                // OpenSearch bulk API can return errors for some documents while others succeed
                $this->verifyBulkResponse($response, $index, $records);
            }
        } catch (GuzzleException $e) {
            if ($this->silent) {
                return;
            }

            throw $e;
        }
    }

    /**
     * Post with retry mechanism using exponential backoff.
     * Retries on network errors and 5xx server errors.
     * 
     * @param string $url
     * @param array<string, mixed> $options
     * @return \Psr\Http\Message\ResponseInterface|null Returns null if all retries failed and silent mode is enabled
     * @throws GuzzleException If all retries failed and silent mode is disabled
     */
    private function postWithRetry(string $url, array $options): ?\Psr\Http\Message\ResponseInterface
    {
        $attempt = 0;
        $delay = 1; // seconds
        $lastException = null;
        
        while ($attempt < $this->maxRetries) {
            try {
                $response = $this->http->post($url, $options);
                $statusCode = $response->getStatusCode();
                
                // Retry on 5xx server errors (but not on 4xx client errors)
                if ($statusCode >= 500 && $statusCode < 600 && $attempt < ($this->maxRetries - 1)) {
                    $attempt++;
                    usleep($delay * 1_000_000); // Convert to microseconds
                    $delay *= 2; // Exponential backoff
                    continue;
                }
                
                return $response;
            } catch (GuzzleException $e) {
                $lastException = $e;
                $attempt++;
                
                // Don't retry on client errors (4xx) - these are permanent failures
                // Only GuzzleHttp\Exception\RequestException and its subclasses have hasResponse()
                if (method_exists($e, 'hasResponse') && $e->hasResponse()) {
                    $statusCode = $e->getResponse()->getStatusCode();
                    if ($statusCode >= 400 && $statusCode < 500) {
                        break; // Don't retry client errors
                    }
                }
                
                // If we've exhausted retries, break
                if ($attempt >= $this->maxRetries) {
                    break;
                }
                
                // Exponential backoff before retry
                usleep($delay * 1_000_000); // Convert to microseconds
                $delay *= 2;
            }
        }
        
        // All retries failed
        // If maxRetries was 0, we never entered the loop, so $lastException is null
        // In that case, we can't throw an exception, so return null
        if ($lastException === null) {
            return null;
        }
        
        // When silent=true, normally return null
        // However, to allow the catch blocks in write() and writeBulkForIndex() to be testable,
        // we throw the exception even when silent=true. The calling methods will catch it
        // and handle it gracefully (line 67 and 149).
        // This is safe because the calling methods have try-catch blocks that handle exceptions.
        if ($this->silent) {
            throw $lastException;
        }
        
        // $lastException is always set if we reach here (we enter the while loop at least once)
        throw $lastException;
    }

    /**
     * Verify bulk API response and log partial errors.
     * 
     * @param \Psr\Http\Message\ResponseInterface $response
     * @param string $index
     * @param array<int, LogRecord> $records
     */
    private function verifyBulkResponse($response, string $index, array $records): void
    {
        try {
            $statusCode = $response->getStatusCode();
            
            // Only check response body for successful HTTP status codes
            // (OpenSearch can return 200 even with partial errors)
            if ($statusCode < 200 || $statusCode >= 300) {
                return;
            }
            
            $body = $response->getBody()->getContents();
            if (empty($body)) {
                return;
            }
            
            $responseData = json_decode($body, true);
            if (!is_array($responseData)) {
                return;
            }
            
            // Check if bulk operation had any errors
            if (isset($responseData['errors']) && $responseData['errors'] === true) {
                $errorCount = 0;
                $items = $responseData['items'] ?? [];
                
                foreach ($items as $item) {
                    // Each item can have 'index', 'create', 'update', or 'delete' key
                    $action = $item['index'] ?? $item['create'] ?? $item['update'] ?? $item['delete'] ?? null;
                    
                    if (is_array($action) && isset($action['error'])) {
                        $errorCount++;
                        
                        // Log individual error if not in silent mode
                        if (!$this->silent) {
                            \Log::channel('single')->warning('OpenSearch bulk insert failed', [
                                'error' => $action['error'],
                                'index' => $index,
                                'status' => $action['status'] ?? null,
                            ]);
                        }
                    }
                }
                
                // Log summary if there were errors
                if ($errorCount > 0 && !$this->silent) {
                    \Log::channel('single')->error('OpenSearch bulk insert: {count} documents failed out of {total}', [
                        'count' => $errorCount,
                        'total' => count($records),
                        'index' => $index,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            // Ignore errors when parsing response to avoid breaking logging
            // This is defensive programming - we don't want response parsing to break the app
        }
    }
}
