<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Logging Channels
    |--------------------------------------------------------------------------
    |
    | Configure which channels should be used by default for the stack.
    | You can override this in your .env with LOG_STACK.
    |
    */
    'default_stack' => env('LOG_STACK', 'single,opensearch'),

    /*
    |--------------------------------------------------------------------------
    | Service Name
    |--------------------------------------------------------------------------
    |
    | The name of this service/application. Used to identify logs from different
    | services in a multi-service environment. Falls back to config('app.name').
    |
    */
    'service_name' => env('LOG_SERVICE_NAME', null),

    /*
    |--------------------------------------------------------------------------
    | OpenSearch Configuration
    |--------------------------------------------------------------------------
    */
    'opensearch' => [
        'url' => env('OPENSEARCH_URL', 'http://localhost:9200'),
        'default_index' => env('OPENSEARCH_DEFAULT_INDEX', 'general_log'),
        'username' => env('OPENSEARCH_USERNAME'),
        'password' => env('OPENSEARCH_PASSWORD'),
        'verify_tls' => filter_var(env('OPENSEARCH_VERIFY_TLS', true), FILTER_VALIDATE_BOOL),
        'timeout' => (int) env('OPENSEARCH_TIMEOUT', 2),
        'silent' => filter_var(env('OPENSEARCH_SILENT', true), FILTER_VALIDATE_BOOL),
    ],

    /*
    |--------------------------------------------------------------------------
    | Kafka Configuration
    |--------------------------------------------------------------------------
    */
    'kafka' => [
        'rest_proxy_url' => env('KAFKA_REST_PROXY_URL', 'http://localhost:8082'),
        'topic' => env('KAFKA_LOG_TOPIC', 'laravel-logs'),
        'timeout' => (int) env('KAFKA_LOG_TIMEOUT', 2),
        'silent' => filter_var(env('KAFKA_LOG_SILENT', true), FILTER_VALIDATE_BOOL),
    ],

    /*
    |--------------------------------------------------------------------------
    | Job Logging
    |--------------------------------------------------------------------------
    |
    | Automatically log job execution events (JobProcessed, JobFailed) to job_log index.
    | Tracks job name, duration, status, attempts, memory usage, and more.
    |
    */
    'job' => [
        'enabled' => filter_var(env('LOG_JOB_ENABLED', true), FILTER_VALIDATE_BOOL),
    ],

    /*
    |--------------------------------------------------------------------------
    | ORM/Database Query Logging
    |--------------------------------------------------------------------------
    |
    | Automatically log all database queries and Eloquent model events to orm_log index.
    | This unified logging combines QueryExecuted events with Eloquent model events
    | (created, updated, deleted) into a single log entry per operation.
    |
    | When enabled, logs include:
    | - Query SQL, bindings, duration, slow query detection (from QueryExecuted)
    | - Previous value and after value (from Eloquent events)
    |
    | WARNING: This can generate a lot of logs. Use with caution in production.
    |
    */
    'orm' => [
        'enabled' => filter_var(env('LOG_ORM_ENABLED', false), FILTER_VALIDATE_BOOL),
        'log_read_operations' => filter_var(env('LOG_ORM_LOG_READ_OPERATIONS', false), FILTER_VALIDATE_BOOL), // Log SELECT queries
        'slow_query_threshold_ms' => (int) env('LOG_ORM_SLOW_QUERY_THRESHOLD_MS', 1000),
        'ignore_patterns' => [
            'select * from `migrations`',
            'select * from `jobs`',
            'select * from `failed_jobs`',
            'select * from `job_batches`',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Middleware Configuration
    |--------------------------------------------------------------------------
    */
    'middleware' => [
        'request_id' => [
            'enabled' => env('LOG_MIDDLEWARE_REQUEST_ID', true),
            'header' => env('LOG_REQUEST_ID_HEADER', 'X-Request-Id'),
        ],
        'api_access_log' => [
            'enabled' => env('LOG_MIDDLEWARE_API_ACCESS', true),
        ],
        'flush_deferred_logs' => [
            'enabled' => env('LOG_MIDDLEWARE_FLUSH_DEFERRED', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Size Limits
    |--------------------------------------------------------------------------
    |
    | Maximum sizes for log content to prevent huge logs and memory issues.
    | All sizes are in bytes.
    |
    */
    'limits' => [
        'max_request_body_size' => (int) env('LOG_MAX_REQUEST_BODY_SIZE', 10240), // 10KB
        'max_response_body_size' => (int) env('LOG_MAX_RESPONSE_BODY_SIZE', 10240), // 10KB
        'max_bindings_size' => (int) env('LOG_MAX_BINDINGS_SIZE', 2048), // 2KB
    ],

    /*
    |--------------------------------------------------------------------------
    | Deferred Logger Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the DeferredLogger that accumulates logs in memory
    | and writes them at the end of the request/job.
    |
    */
    'deferred' => [
        /*
        | Maximum number of logs to accumulate in memory before automatically
        | flushing. When this limit is reached, all accumulated logs are
        | flushed and execution continues normally.
        |
        | Set to 0 or null to disable the limit (not recommended in production).
        | Default: 1000 logs (~1 MB based on average log size)
        |
        */
        'max_logs' => (int) env('LOG_DEFERRED_MAX_LOGS', 1000),

        /*
        | Whether to log a warning when the limit is reached and auto-flush
        | is triggered. Useful for monitoring memory usage patterns.
        |
        */
        'warn_on_limit' => filter_var(env('LOG_DEFERRED_WARN_ON_LIMIT', true), FILTER_VALIDATE_BOOL),
    ],

    /*
    |--------------------------------------------------------------------------
    | index_file (new file-based implementation)
    |--------------------------------------------------------------------------
    |
    | The new file logging is provided via the Laravel logging channel "index_file".
    | Enable it by adding "index_file" to your LOG_STACK (or stack channels).
    |
    | It writes JSONL (one JSON object per line) files under:
    |
    |   storage/logs/laravel-logger/<index>-YYYY-MM-DD.jsonl
    |
    | "single" remains the legacy Laravel stack-based logging and is enabled
    | simply by having "single" in your stack channels.
    |
    | Retention:
    | - keep last N days (today + previous N-1 days)
    | - set 0 to disable pruning
    |
    */
    'index_file' => [
        'retention_days' => (int) env('LOG_INDEX_FILE_RETENTION_DAYS', 14),
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Builders
    |--------------------------------------------------------------------------
    |
    | You can customize the document/value builders for OpenSearch and Kafka.
    | Set these to your custom class names.
    |
    */
    'builders' => [
        'opensearch' => \Ermetix\LaravelLogger\Logging\Builders\DefaultOpenSearchDocumentBuilder::class,
        'kafka' => \Ermetix\LaravelLogger\Logging\Builders\IndexKeyKafkaValueBuilder::class,
    ],
];
