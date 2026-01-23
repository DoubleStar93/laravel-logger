<?php

namespace Ermetix\LaravelLogger\Listeners;

use Ermetix\LaravelLogger\Facades\LaravelLogger as Log;
use Ermetix\LaravelLogger\Support\Logging\Objects\OrmLogObject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Automatically log Eloquent model events (creating, created, updating, updated, deleting, deleted).
 * 
 * This listener captures model changes with previous_value and after_value
 * for audit trail purposes.
 * 
 * Register this as a global observer in LaravelLoggerServiceProvider.
 */
class LogModelEvents
{
    /**
     * Handle the model created event.
     */
    public function created(Model $model): void
    {
        if (!config('laravel-logger.orm.model_events_enabled', false)) {
            return;
        }

        Log::orm(
            new OrmLogObject(
                message: 'model_created',
                model: get_class($model),
                modelId: $model->getKey() ? (string) $model->getKey() : null,
                action: 'create',
                query: null, // Not a raw query, it's an Eloquent operation
                queryType: 'INSERT',
                durationMs: null,
                bindings: null,
                connection: $model->getConnectionName(),
                table: $model->getTable(),
                transactionId: $this->getTransactionId($model->getConnectionName()),
                userId: Auth::id() ? (string) Auth::id() : null,
                previousValue: null, // No previous value for create
                afterValue: $model->getAttributes(),
                level: 'info',
            ),
            defer: true,
        );
    }

    /**
     * Handle the model updated event.
     */
    public function updated(Model $model): void
    {
        if (!config('laravel-logger.orm.model_events_enabled', false)) {
            return;
        }

        // Get original attributes (before changes)
        $original = $model->getOriginal();
        
        // Get current attributes (after changes)
        $afterValue = $model->getAttributes();

        Log::orm(
            new OrmLogObject(
                message: 'model_updated',
                model: get_class($model),
                modelId: $model->getKey() ? (string) $model->getKey() : null,
                action: 'update',
                query: null,
                queryType: 'UPDATE',
                durationMs: null,
                bindings: null,
                connection: $model->getConnectionName(),
                table: $model->getTable(),
                transactionId: $this->getTransactionId($model->getConnectionName()),
                userId: Auth::id() ? (string) Auth::id() : null,
                previousValue: $original,
                afterValue: $afterValue,
                level: 'info',
            ),
            defer: true,
        );
    }

    /**
     * Handle the model deleted event.
     */
    public function deleted(Model $model): void
    {
        if (!config('laravel-logger.orm.model_events_enabled', false)) {
            return;
        }

        // Get original attributes before deletion
        $original = $model->getOriginal();

        Log::orm(
            new OrmLogObject(
                message: 'model_deleted',
                model: get_class($model),
                modelId: $model->getKey() ? (string) $model->getKey() : null,
                action: 'delete',
                query: null,
                queryType: 'DELETE',
                durationMs: null,
                bindings: null,
                connection: $model->getConnectionName(),
                table: $model->getTable(),
                transactionId: $this->getTransactionId($model->getConnectionName()),
                userId: Auth::id() ? (string) Auth::id() : null,
                previousValue: $original,
                afterValue: null, // No after value for delete
                level: 'info',
            ),
            defer: true,
        );
    }

    /**
     * Transaction ID storage per connection (shared with LogDatabaseQuery).
     * 
     * @var array<string, string>
     */
    protected static array $transactionIds = [];

    /**
     * Get transaction ID if within a transaction.
     */
    protected function getTransactionId(?string $connectionName): ?string
    {
        $connectionName = $connectionName ?: \Illuminate\Support\Facades\DB::getDefaultConnection();

        try {
            $connection = \Illuminate\Support\Facades\DB::connection($connectionName);
            $transactionLevel = $connection->transactionLevel();
            
            if ($transactionLevel > 0) {
                $key = $connectionName . ':' . $transactionLevel;
                
                // Use shared transaction ID cache
                if (!isset(self::$transactionIds[$key])) {
                    self::$transactionIds[$key] = 'txn-' . \Illuminate\Support\Str::uuid()->toString();
                }
                
                return self::$transactionIds[$key];
            } else {
                unset(self::$transactionIds[$connectionName . ':' . $transactionLevel]);
            }
        } catch (\Throwable $e) {
            // Ignore errors
        }

        return null;
    }
}
