# Automatic Configuration

The `ermetix/laravel-logger` package can automatically configure your Laravel application with a single command.

## Installation Command

```bash
php artisan laravel-logger:install
```

This command will automatically:

1. ✅ **Publish configuration** (`config/laravel-logger.php`)
2. ✅ **Add logging channels** to `config/logging.php`:
   - `kafka` channel
   - `opensearch` channel
   - `index_file` channel
3. ✅ **Add middleware** to `bootstrap/app.php`:
   - `RequestId` middleware
   - `ApiAccessLog` middleware
   - `FlushDeferredLogs` middleware
4. ✅ **Add exception handling** to `bootstrap/app.php`:
   - Automatic error logging to OpenSearch
   - Fatal error detection and immediate logging
   - Deferred logging for normal exceptions

## What Gets Modified

### `config/logging.php`

To avoid duplicating the exact configuration in multiple docs, the single source of truth for the inserted channels is:

- `packages/laravel-logger/stubs/logging-channels.stub`

### `bootstrap/app.php`

To avoid duplicating large code blocks, the single sources of truth are:

- `packages/laravel-logger/stubs/bootstrap-app.stub` (middleware)
- `packages/laravel-logger/stubs/bootstrap-exceptions.stub` (exception logging)

## Force Overwrite

If configuration already exists, use `--force` to overwrite:

```bash
php artisan laravel-logger:install --force
```

## Manual Configuration

If you prefer to configure manually, see [INSTALLATION.md](../INSTALLATION.md) for step-by-step instructions.

## Benefits

- ✅ **Zero configuration** - Everything set up automatically
- ✅ **Consistent setup** - Same configuration across all projects
- ✅ **Time saving** - No need to copy-paste configuration
- ✅ **Error-free** - Reduces manual configuration errors
