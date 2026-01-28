# Laravel Logger Package

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.2-777BB4.svg)](https://www.php.net/)
[![Laravel Version](https://img.shields.io/badge/laravel-%5E12.0-FF2D20.svg)](https://laravel.com/)
[![Tests](https://img.shields.io/badge/tests-331%20passed-brightgreen.svg)](https://github.com/ermetix/laravel-logger)
[![Coverage](https://img.shields.io/badge/coverage-99.95%25-brightgreen.svg)](https://github.com/ermetix/laravel-logger)

Advanced logging package for Laravel with OpenSearch, Kafka, and typed logging support.

## Requirements

- **PHP**: >= 8.2
- **Laravel**: >= 12.0
- **Extensions**: 
  - `json` (built-in)
  - `curl` (for HTTP requests to OpenSearch/Kafka)
- **Optional** (for code coverage):
  - `pcov` or `xdebug` (see [Testing](#testing) section)

## Quick Start

Get up and running in 3 steps:

```bash
# 1. Install the package
composer require ermetix/laravel-logger

# 2. Run automatic installation
php artisan laravel-logger:install

# 3. Verify installation
php artisan laravel-logger:verify
```

That's it! The package is now configured and ready to use. See [Installation](#installation) for detailed setup instructions.

**For OpenSearch setup**, see the [OpenSearch Setup](#opensearch-setup) section below.

## Features

- ✅ **Typed Logging**: Type-safe logging with dedicated LogObject classes ([Usage Examples](USAGE.md))
- ✅ **OpenSearch Integration**: Dynamic index routing with strict mappings ([OpenSearch Setup](#opensearch-setup))
- ✅ **Kafka Support**: Log to Kafka via REST Proxy ([Kafka Setup](docker/kafka/README.md))
- ✅ **Index File Channel**: Log to JSONL files via `index_file` channel
- ✅ **Deferred Logging**: Non-blocking in-memory log accumulation ([Deferred Logging Guide](docs/AUTO_FLUSH_MECHANISM.md))
- ✅ **Multiple Indices**: api_log, general_log, job_log, integration_log, orm_log, error_log ([Index Schema](docs/opensearch-index-schema.md))
- ✅ **Request Correlation**: Automatic request_id propagation
- ✅ **Error Handling**: Automatic error logging with fatal error support
- ✅ **JSON Pretty Printing**: Automatic formatting of JSON fields (request_body, response_body, headers)

## Installation

### Step 1: Install the Package

**For Local Development (Path Repository):**

Add to your `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "./packages/laravel-logger"
        }
    ],
    "require": {
        "ermetix/laravel-logger": "@dev"
    }
}
```

Then run:

```bash
composer require ermetix/laravel-logger
```

**For Production (Packagist):**

```bash
composer require ermetix/laravel-logger
```

### Step 2: Automatic Installation (Recommended)

Run the installation command to automatically configure everything:

```bash
php artisan laravel-logger:install
```

This command will automatically:
- ✅ Publish configuration file (`config/laravel-logger.php`)
- ✅ Add logging channels to `config/logging.php` (`opensearch`, `kafka`, `index_file`)
- ✅ Add middleware to `bootstrap/app.php` (RequestId, ApiAccessLog, FlushDeferredLogs)
- ✅ Add exception handling to `bootstrap/app.php` (automatic error logging)

**Force overwrite existing configuration:**

```bash
php artisan laravel-logger:install --force
```

### Step 3: Verify Installation

Check that all components are correctly installed:

```bash
php artisan laravel-logger:verify
```

This command verifies:
- Configuration file exists
- Logging channels are configured
- Middleware is registered
- Exception handling is configured

> 📖 **Need more details?** See [Installation Guide](INSTALLATION.md) for comprehensive installation instructions, including manual configuration steps.

## Configuration

### Environment Variables

Add the following variables to your `.env` file:

```env
# Stack Configuration
LOG_STACK="index_file,opensearch,kafka"
LOG_SERVICE_NAME="my-service"
LOG_LEVEL="debug"

# OpenSearch Configuration
OPENSEARCH_URL="http://localhost:9200"
OPENSEARCH_DEFAULT_INDEX="general_log"
OPENSEARCH_VERIFY_TLS=false
OPENSEARCH_TIMEOUT=2
OPENSEARCH_SILENT=true

# Kafka Configuration (optional)
KAFKA_REST_PROXY_URL="http://localhost:8082"
KAFKA_LOG_TOPIC="laravel-logs"
KAFKA_LOG_TIMEOUT=2
KAFKA_LOG_SILENT=true

# ORM Logging (optional)
# When enabled, logs both database queries (QueryExecuted) and Eloquent model events
# into unified orm_log entries. Each ORM operation generates a single log that combines
# query SQL/bindings/duration with model previous_value/after_value.
LOG_ORM_ENABLED=false
LOG_ORM_SLOW_QUERY_THRESHOLD_MS=1000

# Middleware Configuration
LOG_MIDDLEWARE_REQUEST_ID=true
LOG_REQUEST_ID_HEADER="X-Request-Id"
LOG_MIDDLEWARE_API_ACCESS=true
LOG_MIDDLEWARE_FLUSH_DEFERRED=true

# File Logging Retention
LOG_INDEX_FILE_RETENTION_DAYS=14

# Log Size Limits
LOG_MAX_REQUEST_BODY_SIZE=10240
LOG_MAX_RESPONSE_BODY_SIZE=10240
LOG_MAX_BINDINGS_SIZE=2048

# Deferred Logger Configuration (Memory Management)
LOG_DEFERRED_MAX_LOGS=1000
LOG_DEFERRED_WARN_ON_LIMIT=true
```

**Note:** For local Docker development, set `OPENSEARCH_VERIFY_TLS=false` since Docker doesn't use HTTPS.

See [env.example](env.example) for all available configuration options.

> 📖 **Want to learn more?** See [Configuration Guide](docs/AUTOMATIC_CONFIGURATION.md) for detailed configuration options and advanced settings.

### Manual Configuration (Alternative)

If you prefer to configure manually:

```bash
# Publish configuration
php artisan vendor:publish --tag=laravel-logger-config

# Publish OpenSearch templates (optional - scripts can find them in package)
php artisan vendor:publish --tag=laravel-logger-opensearch

# Publish Docker setup scripts (optional)
php artisan vendor:publish --tag=laravel-logger-docker
```

Then manually add the configuration as shown in the stubs:
- `packages/laravel-logger/stubs/logging-channels.stub` - Logging channels
- `packages/laravel-logger/stubs/bootstrap-app.stub` - Middleware configuration
- `packages/laravel-logger/stubs/bootstrap-exceptions.stub` - Exception handling

> 📖 **Need help?** See [Installation Guide](INSTALLATION.md) for step-by-step manual installation instructions.

## OpenSearch Setup

### Prerequisites

You need Docker and Docker Compose installed on your system.

> 📖 **Detailed guide:** See [OpenSearch Setup Guide](docker/opensearch/README.md) for comprehensive OpenSearch configuration and troubleshooting.

### Step 1: Start OpenSearch

The package includes a Docker Compose configuration. You can use it in two ways:

**Option A: Copy to project root (Recommended)**

```bash
cp packages/laravel-logger/docker/opensearch/docker-compose.example.yml docker-compose.yml
docker-compose up -d
```

**Option B: Use directly from package directory**

```bash
cd packages/laravel-logger/docker/opensearch
docker-compose -f docker-compose.example.yml up -d
```

### Step 2: Verify OpenSearch is Running

```bash
curl http://localhost:9200
```

You should see a JSON response with cluster information.

### Step 3: Apply Index Templates and Policies

**⚠️ IMPORTANT:** Before logging, you must apply the index templates. Without this step, logs will not be indexed correctly!

**Setup with OpenSearch Dashboards (Recommended):**

```bash
php packages/laravel-logger/docker/opensearch/setup.php --with-dashboards
```

This command will:
- ✅ Apply 6 index templates (api_log, general_log, job_log, integration_log, orm_log, error_log)
- ✅ Apply ISM retention policy
- ✅ Create index patterns in OpenSearch Dashboards
- ✅ Configure default sort order (timestamp descending)
- ✅ Create test documents for field discovery

**Setup without Dashboards:**

```bash
php packages/laravel-logger/docker/opensearch/setup.php
```

**Nota:** Lo script PHP è cross-platform e funziona su Windows, Linux e macOS.

### Step 4: Access OpenSearch Dashboards

After running the setup script:

1. Open OpenSearch Dashboards: http://localhost:5601
2. Go to **Discover** in the left menu
3. Select an index pattern (e.g., `api_log*`)
4. Configure visible columns manually (see below)

**Note:** OpenSearch Dashboards doesn't allow setting default visible columns via API. You need to configure them manually:

1. In Discover, click the "+" button next to fields in the "Available fields" sidebar
2. Add these recommended fields:
   - **api_log***: `@timestamp`, `method`, `path`, `route_name`, `status`, `duration_ms`, `user_id`, `ip`, `request_id`
   - **general_log***: `@timestamp`, `message`, `event`, `entity_type`, `entity_id`, `action_type`, `user_id`, `level`, `request_id`
   - **job_log***: `@timestamp`, `job`, `command`, `status`, `duration_ms`, `exit_code`, `frequency`, `output`, `level`, `request_id`
   - **integration_log***: `@timestamp`, `integration_name`, `url`, `method`, `status`, `duration_ms`, `level`, `request_id`
   - **orm_log***: `@timestamp`, `model`, `action`, `query_type`, `table`, `duration_ms`, `is_slow_query`, `user_id`, `request_id`
   - **error_log***: `@timestamp`, `exception_class`, `code`, `level`, `context_route`, `context_method`, `context_url`, `context_user_id`, `request_id`
3. (Optional) Save the view: Click "Save" in the top right to reuse this configuration

> 📖 **Detailed instructions:** See [Configure Discover Columns](docs/configure-discover-columns.md) for step-by-step guide on setting up visible columns in OpenSearch Dashboards.

### Step 5: Verify Templates

Check that templates were applied correctly:

```bash
# List all templates
curl "http://localhost:9200/_index_template?pretty"

# Check a specific template
curl "http://localhost:9200/_index_template/api_log-template?pretty"

# List all indices
curl "http://localhost:9200/_cat/indices?v"
```

### OpenSearch Commands Reference

**Start OpenSearch:**
```bash
docker-compose up -d
```

**Stop OpenSearch:**
```bash
docker-compose down
```

**Stop and remove volumes (delete all data):**
```bash
docker-compose down -v
```

**View logs:**
```bash
docker-compose logs -f opensearch
docker-compose logs -f opensearch-dashboards
```

**Restart OpenSearch:**
```bash
docker-compose restart opensearch
docker-compose restart opensearch-dashboards
```

**Regenerate OpenSearch completely (fresh start):**
```bash
# Stop and remove containers
docker-compose down

# Remove volumes (deletes all data)
docker volume rm laravel_logger_example_opensearch-data

# Start fresh
docker-compose up -d

# Wait for OpenSearch to be ready (10 seconds)
sleep 10

# Run setup
php packages/laravel-logger/docker/opensearch/setup.php --with-dashboards
```

**Check OpenSearch health:**
```bash
curl http://localhost:9200/_cluster/health?pretty
```

**Search logs:**
```bash
# Search all logs in an index
curl "http://localhost:9200/api_log-*/_search?pretty"

# Search with query
curl -X POST "http://localhost:9200/api_log-*/_search?pretty" \
  -H "Content-Type: application/json" \
  -d '{"query": {"match": {"status": 200}}}'
```

**Delete old indices:**
```bash
# Delete indices older than 30 days
curl -X DELETE "http://localhost:9200/api_log-2026.01.*"
```

### Manual Template Application (Alternative)

If you prefer to apply templates manually:

> 📖 **Note:** For most users, the automated setup script is recommended. See [OpenSearch Setup Guide](docker/opensearch/README.md) for more details.

```bash
# Apply all templates
curl -X PUT "http://localhost:9200/_index_template/api_log-template" \
  -H "Content-Type: application/json" \
  -d @packages/laravel-logger/resources/opensearch/index-templates/api_log-template.json

curl -X PUT "http://localhost:9200/_index_template/general_log-template" \
  -H "Content-Type: application/json" \
  -d @packages/laravel-logger/resources/opensearch/index-templates/general_log-template.json

curl -X PUT "http://localhost:9200/_index_template/job_log-template" \
  -H "Content-Type: application/json" \
  -d @packages/laravel-logger/resources/opensearch/index-templates/job_log-template.json

curl -X PUT "http://localhost:9200/_index_template/integration_log-template" \
  -H "Content-Type: application/json" \
  -d @packages/laravel-logger/resources/opensearch/index-templates/integration_log-template.json

curl -X PUT "http://localhost:9200/_index_template/orm_log-template" \
  -H "Content-Type: application/json" \
  -d @packages/laravel-logger/resources/opensearch/index-templates/orm_log-template.json

curl -X PUT "http://localhost:9200/_index_template/error_log-template" \
  -H "Content-Type: application/json" \
  -d @packages/laravel-logger/resources/opensearch/index-templates/error_log-template.json

# Apply ISM retention policy
curl -X PUT "http://localhost:9200/_plugins/_ism/policies/logs-retention-policy" \
  -H "Content-Type: application/json" \
  -d @packages/laravel-logger/resources/opensearch/ism/logs-retention-policy.json
```

## Usage

### Basic Usage

```php
use Ermetix\LaravelLogger\Facades\LaravelLogger as Log;
use Ermetix\LaravelLogger\Support\Logging\Objects\GeneralLogObject;

// Deferred logging (non-blocking, default)
Log::general(new GeneralLogObject(
    message: 'user_profile_updated',
    event: 'profile_updated',
    entityType: 'user',
    entityId: '123',
    userId: '456',
    level: 'info',
));

// Immediate logging (blocking)
Log::general(new GeneralLogObject(
    message: 'critical_event',
    event: 'payment_processed',
    level: 'info',
), defer: false);
```

### Available Log Types

- `Log::general(GeneralLogObject)` - General application events
- `Log::api(ApiLogObject)` - API requests/responses (auto-logged via middleware)
- `Log::job(JobLogObject)` - Jobs and scheduled tasks (auto-logged when enabled, or set `frequency` for cron jobs)
- `Log::cron(JobLogObject)` - Deprecated: use `Log::job()` instead (kept for backward compatibility)
- `Log::integration(IntegrationLogObject)` - External API integrations
- `Log::orm(OrmLogObject)` - Database/ORM operations (if enabled)
- `Log::error(ErrorLogObject)` - Errors and exceptions (auto-logged)

> 📖 **More examples:** See [Usage Examples](USAGE.md) for comprehensive code examples and usage patterns for all log types.

### Example: API Logging

API requests are automatically logged by the `ApiAccessLog` middleware. No manual logging needed.

> 📖 **Learn more:** See [Usage Examples - API Logging](USAGE.md#api-logging) for details on how API logging works and how to customize it.

### Example: Integration Logging

```php
use Ermetix\LaravelLogger\Facades\LaravelLogger as Log;
use Ermetix\LaravelLogger\Support\Logging\Objects\IntegrationLogObject;

Log::integration(new IntegrationLogObject(
    message: 'external_api_call',
    integrationName: 'stripe',
    url: 'https://api.stripe.com/v1/charges',
    method: 'POST',
    status: 200,
    durationMs: 350,
    requestBody: json_encode(['amount' => 1000]),
    responseBody: json_encode(['id' => 'ch_123']),
));
```

### Example: ORM Logging

Enable ORM logging in `.env`:

```env
LOG_ORM_ENABLED=true
```

ORM operations are automatically logged when enabled.

> 📖 **Learn more:** See [Usage Examples - ORM Logging](USAGE.md#orm-logging) for details on ORM logging configuration and what gets logged.

### Example: Job Logging

Job execution events are **automatically logged** when enabled (default: enabled). The `LogJobEvents` listener tracks:
- Job name and ID
- Duration and memory usage
- Status (success/failed)
- Attempts and queue name
- Whether it's a cron job (scheduled command)
- Error messages for failed jobs

Enable/disable in `.env`:
```env
LOG_JOB_ENABLED=true  # Default: true
```

You can also manually log job events using `Log::job()`:
```php
use Ermetix\LaravelLogger\Facades\LaravelLogger as Log;
use Ermetix\LaravelLogger\Support\Logging\Objects\JobLogObject;

Log::job(new JobLogObject(
    message: 'custom_job_event',
    job: 'MyCustomJob',
    status: 'success',
    durationMs: 1500,
    frequency: null,
    output: 'Custom output message',
));
```

### Example: Error Logging

Errors and exceptions are automatically logged by the exception handler. No manual logging needed.

> 📖 **Learn more:** See [Usage Examples - Error Logging](USAGE.md#error-logging) for details on automatic error logging and how to log custom errors.

## Testing

Run the test suite:

```bash
composer test
```

Or with Pest directly:

```bash
vendor/bin/pest
```

### Code Coverage

To check code coverage, you need to install a coverage driver first:

**Option 1: Install PCOV (Recommended - faster)**

```bash
# On macOS with Homebrew
pecl install pcov

# On Linux
sudo pecl install pcov

# Enable in php.ini
echo "extension=pcov.so" >> php.ini
echo "pcov.enabled=1" >> php.ini
```

**Option 2: Install Xdebug**

```bash
# On macOS with Homebrew
pecl install xdebug

# On Linux
sudo pecl install xdebug

# Enable in php.ini
echo "zend_extension=xdebug.so" >> php.ini
echo "xdebug.mode=coverage" >> php.ini
```

**Verify installation:**

```bash
php -m | grep -E "pcov|xdebug"
```

**Run coverage:**

```bash
# Basic coverage report (terminal, summary only)
composer test-coverage

# Detailed text coverage report (shows all files)
composer test-coverage-text

# HTML coverage report (generates coverage/ directory)
composer test-coverage-html

# Coverage with minimum threshold (fails if below 80%)
composer test-coverage-min

# Clover XML format (for CI/CD tools)
composer test-coverage-clover
```

The coverage report will show:
- Percentage of code covered
- Files and classes covered
- Lines covered/uncovered
- Methods covered/uncovered

> 📖 **Current coverage:** The package maintains **99.95% code coverage** (1884/1885 lines). See the generated HTML report in `coverage/index.html` for detailed coverage information.

## Troubleshooting

### Logs not appearing in OpenSearch

1. **Verify OpenSearch is running:**
   ```bash
   curl http://localhost:9200
   ```

2. **Check templates are applied:**
   ```bash
   curl "http://localhost:9200/_index_template?pretty"
   ```

3. **Verify configuration:**
   ```bash
   php artisan laravel-logger:verify
   ```

4. **Check Laravel logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

5. **Test logging manually:**
   ```php
   Log::general(new GeneralLogObject(
       message: 'test_log',
       level: 'info',
   ));
   ```

> 📖 **More help:** See [OpenSearch Setup Guide](docker/opensearch/README.md#troubleshooting) for detailed troubleshooting steps.

### OpenSearch connection errors

- Check `OPENSEARCH_URL` in `.env`
- Verify OpenSearch is accessible: `curl http://localhost:9200`
- For Docker: ensure containers are running: `docker-compose ps`
- Check timeout settings: increase `OPENSEARCH_TIMEOUT` if needed

> 📖 **Configuration help:** See [Configuration Guide](docs/AUTOMATIC_CONFIGURATION.md) for all available environment variables.

### Fields not visible in OpenSearch Dashboards

- Run the setup script: `php packages/laravel-logger/docker/opensearch/setup.php --with-dashboards`
- Refresh field list in Dashboards: Go to Management → Index Patterns → Refresh
- Manually add columns in Discover (see [OpenSearch Setup - Step 4](#step-4-access-opensearch-dashboards) section above)

> 📖 **Detailed guide:** See [Configure Discover Columns](docs/configure-discover-columns.md) for step-by-step instructions on setting up visible columns.

## Documentation

### Getting Started
- [Installation Guide](INSTALLATION.md) - Detailed step-by-step installation instructions
- [Quick Start](#quick-start) - Get up and running in 3 steps (see above)
- [Configuration Guide](docs/AUTOMATIC_CONFIGURATION.md) - Advanced configuration options

### Usage & Examples
- [Usage Examples](USAGE.md) - Comprehensive code examples and patterns for all log types
- [Logging Usage Guide](docs/logging-usage.md) - Detailed usage patterns and best practices
- [Deferred Logging](docs/AUTO_FLUSH_MECHANISM.md) - Understanding deferred logging and auto-flush mechanism

### OpenSearch
- [OpenSearch Setup Guide](docker/opensearch/README.md) - Complete OpenSearch setup and configuration
- [OpenSearch Index Schema](docs/opensearch-index-schema.md) - Understanding index structure and mappings
- [Configure Discover Columns](docs/configure-discover-columns.md) - Setting up visible columns in Dashboards
- [OpenSearch Design](docs/opensearch-logging-design.md) - Architecture and design decisions

### Migration & Advanced
- [Migration Guide](MIGRATION.md) - Migrating from other logging solutions
- [Environment Variables](env.example) - All available configuration options
- [Full Documentation Index](docs/README.md) - Complete documentation index

### Architecture & Design
- [OpenSearch Logging Design](docs/opensearch-logging-design.md) - Overall architecture and design decisions
- [OpenSearch Diagrams](docs/opensearch-diagram.md) - Visual architecture diagrams
- [OpenSearch Indices Diagram](docs/opensearch-indices-diagram.md) - Detailed flow diagram including deferred logging
- [Docker Structure](docs/DOCKER_STRUCTURE.md) - Docker setup and structure

### Additional Resources
- [Kafka Setup](docker/kafka/README.md) - Kafka REST Proxy setup and configuration
- [Package Summary](PACKAGE_SUMMARY.md) - Package structure overview

## License

MIT
