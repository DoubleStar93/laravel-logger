# Installation Guide

## Step 1: Install the Package

Add the package to your `composer.json`:

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

## Step 2: Install Package Configuration

**Automatic Installation (Recommended):**

```bash
php artisan laravel-logger:install -n
```

This command will:
- ✅ Publish configuration file
- ✅ Add logging channels to `config/logging.php` (`opensearch`, `kafka`, `index_file`)
- ✅ Add middleware configuration to `bootstrap/app.php`
- ✅ Add exception handling to `bootstrap/app.php`
- ✅ Optionally publish OpenSearch templates and Docker scripts (OpenSearch + Kafka)

**Manual Installation:**

If you prefer to configure manually:

```bash
# Publish configuration
php artisan vendor:publish --tag=laravel-logger-config

# Publish OpenSearch templates (optional, scripts can find them in package)
php artisan vendor:publish --tag=laravel-logger-opensearch

# Publish Docker setup scripts (optional)
php artisan vendor:publish --tag=laravel-logger-docker
```

Then manually add the configuration as shown below.

## Step 3: Configure Logging Channels (if not using automatic install)

To avoid duplicating the exact channel configuration in multiple files, use the provided stub as the single source of truth:

- `packages/laravel-logger/stubs/logging-channels.stub`

It contains the latest definitions for:
- `opensearch`
- `kafka`
- `index_file`

## Step 4: Register Middleware (if not using automatic install)

To avoid copy-pasting large blocks, use the provided stubs as the single source of truth:

- `packages/laravel-logger/stubs/bootstrap-app.stub` (middleware)
- `packages/laravel-logger/stubs/bootstrap-exceptions.stub` (exception logging)

## Step 5: Set Up OpenSearch

Use the dedicated Docker guide (single source of truth):

- `packages/laravel-logger/docker/opensearch/README.md`

## Step 5b: Set Up Kafka (optional)

Use the dedicated Docker guide (single source of truth):

- `packages/laravel-logger/docker/kafka/README.md`

## Step 6: Verify Installation

Verify that the installation was successful:

```bash
# Verify all components are correctly installed
php artisan laravel-logger:verify
```

This command checks:
- ✅ Package installation
- ✅ ServiceProvider registration
- ✅ Config file
- ✅ Logging channels
- ✅ Middleware configuration
- ✅ Exception handling
- ✅ Artisan commands
- ✅ Container bindings

## Step 7: Test the Setup

The package includes Artisan commands for testing:

```bash
# Create test log entries
php artisan opensearch:test

# Verify data in OpenSearch
php artisan opensearch:verify
```

## Step 8: Use Typed Logging

```php
use Ermetix\LaravelLogger\Facades\LaravelLogger as Log;
use Ermetix\LaravelLogger\Support\Logging\Objects\GeneralLogObject;

Log::general(new GeneralLogObject(
    message: 'user_profile_updated',
    event: 'profile_updated',
    userId: '123',
    level: 'info',
)); // default: deferred (non-blocking)
```

## Environment Variables

To avoid duplicating env variables in multiple files, copy what you need from:

- `packages/laravel-logger/env.example`
