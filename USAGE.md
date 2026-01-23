# Usage Examples

## Typed Logging

All logging methods require a specific `LogObject` type, ensuring type safety:

```php
use Ermetix\LaravelLogger\Facades\LaravelLogger as Log;
use Ermetix\LaravelLogger\Support\Logging\Objects\GeneralLogObject;
use Ermetix\LaravelLogger\Support\Logging\Objects\ApiLogObject;
use Ermetix\LaravelLogger\Support\Logging\Objects\CronLogObject;
use Ermetix\LaravelLogger\Support\Logging\Objects\IntegrationLogObject;
use Ermetix\LaravelLogger\Support\Logging\Objects\OrmLogObject;
use Ermetix\LaravelLogger\Support\Logging\Objects\ErrorLogObject;

// General log
Log::general(new GeneralLogObject(
    message: 'user_profile_updated',
    event: 'profile_updated',
    userId: '123',
    entityType: 'user',
    entityId: '456',
    level: 'info',
));

// API log (usually auto-logged via middleware)
Log::api(new ApiLogObject(
    message: 'api_access',
    method: 'POST',
    path: '/api/users',
    routeName: 'users.store',
    status: 201,
    durationMs: 45,
    ip: '192.168.1.1',
    userId: '123',
));

// Cron log
Log::cron(new CronLogObject(
    message: 'scheduled_task_completed',
    job: 'SendDailyReport',
    command: 'app:send-daily-report',
    status: 'success',
    durationMs: 1200,
    exitCode: 0,
    memoryPeakMb: 128.5,
));

// Integration log
Log::integration(new IntegrationLogObject(
    message: 'external_api_call',
    integrationName: 'payment_gateway',
    url: 'https://api.payment.com/charge',
    method: 'POST',
    status: 200,
    durationMs: 350,
    requestBody: json_encode(['amount' => 100]),
    responseBody: json_encode(['transaction_id' => 'tx_123']),
));

// ORM log
Log::orm(new OrmLogObject(
    message: 'user_updated',
    model: 'User',
    action: 'update',
    query: 'UPDATE users SET email = ? WHERE id = ?',
    durationMs: 12,
    bindings: json_encode(['new@example.com', 123]),
    connection: 'mysql',
    table: 'users',
    userId: '123',
    previousValue: ['email' => 'old@example.com'],
    afterValue: ['email' => 'new@example.com'],
));

// Error log (usually auto-logged via exception handler)
Log::error(new ErrorLogObject(
    message: 'Database connection failed',
    stackTrace: '...',
    exceptionClass: 'PDOException',
    file: '/app/Models/User.php',
    line: 45,
    code: 0,
    userId: '123',
    route: 'users.index',
    method: 'GET',
    url: 'https://example.com/users',
    level: 'error',
));
```

## Deferred Logging

By default, logging is **deferred** (`defer: true`) to avoid blocking the request.
Set `defer: false` to write immediately.

```php
// Default (non-blocking): logs are written at the end of the lifecycle
Log::general(new GeneralLogObject(
    message: 'heavy_operation_completed',
    event: 'data_processed',
));

// Immediate (blocking): logs are written right away
Log::general(new GeneralLogObject(
    message: 'critical_event',
    event: 'payment_processed',
), defer: false);
```

## index_file (JSONL files)

Enable file logging by adding `index_file` to your stack (see `env.example`):

- Output path: `storage/logs/laravel-logger/<log_index>-YYYY-MM-DD.jsonl`
- Retention: `LOG_INDEX_FILE_RETENTION_DAYS`

This avoids duplicating file logging outside Laravel channels and keeps behavior consistent with other outputs (Kafka/OpenSearch).

## Custom Builders

You can customize how documents are built for OpenSearch or Kafka:

```php
// Custom OpenSearch document builder
namespace App\Logging\Builders;

use Ermetix\LaravelLogger\Logging\Contracts\OpenSearchDocumentBuilder;
use Monolog\LogRecord;

class CustomOpenSearchDocumentBuilder implements OpenSearchDocumentBuilder
{
    public function index(LogRecord $record): string
    {
        // Custom index routing logic
        return 'custom_index';
    }

    public function document(LogRecord $record): array
    {
        // Custom document structure
        return [
            '@timestamp' => $record->datetime->format(DATE_ATOM),
            'custom_field' => 'custom_value',
        ];
    }
}
```

Then configure it in `config/logging.php`:

```php
'opensearch' => [
    // ...
    'document_builder' => \App\Logging\Builders\CustomOpenSearchDocumentBuilder::class,
],
```

For Kafka payload shape and environment variables, use `env.example` as the single source of truth.

## Request ID Correlation

The `RequestId` middleware automatically adds a `request_id` to all logs:

```php
// All logs in the same request will have the same request_id
// This allows you to correlate logs across different indices

// In OpenSearch, you can query:
// request_id: "550e8400-e29b-41d4-a716-446655440000"
```

## Automatic Logging

The package automatically logs:

1. **API Requests**: Via `ApiAccessLog` middleware (logs to `api_log`)
2. **Exceptions**: Via exception handler (logs to `error_log`)
3. **Fatal Errors**: Via shutdown handler (logs to `error_log` immediately)

All automatic logs use deferred logging (except fatal errors) to avoid blocking execution.
