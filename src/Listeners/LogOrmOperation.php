<?php

namespace Ermetix\LaravelLogger\Listeners;

use Ermetix\LaravelLogger\Facades\LaravelLogger as Log;
use Ermetix\LaravelLogger\Support\Config\ConfigReader;
use Ermetix\LaravelLogger\Support\Logging\Objects\OrmLogObject;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Unified listener for ORM operations that combines QueryExecuted and Eloquent model events.
 * 
 * This listener creates a single log entry per ORM operation that includes:
 * - Query SQL, bindings, duration, slow query detection (from QueryExecuted)
 * - Previous value and after value (from Eloquent events)
 * 
 * For raw queries without Eloquent models, only query information is logged.
 */
class LogOrmOperation
{
    /**
     * Temporary storage for QueryExecuted events waiting for Eloquent events.
     * Structure: ['key' => ['query' => ..., 'bindings' => ..., 'durationMs' => ..., ...]]
     * 
     * @var array<string, array>
     */
    protected static array $pendingQueries = [];

    /**
     * Maximum age for pending queries before they're logged without Eloquent event (5 seconds).
     */
    private const MAX_PENDING_AGE = 5;

    /**
     * Handle QueryExecuted event.
     */
    public function handleQueryExecuted(QueryExecuted $event): void
    {
        // Check if ORM logging is enabled
        if (!config('laravel-logger.orm.enabled', false)) {
            return;
        }

        // Skip queries that match ignore patterns
        if ($this->shouldIgnoreQuery($event->sql)) {
            return;
        }

        // Extract query information
        $queryType = $this->extractQueryType($event->sql);
        
        // Skip SELECT queries if read operations logging is disabled
        if ($queryType === 'SELECT' && !config('laravel-logger.orm.log_read_operations', false)) {
            return;
        }

        // For write operations (INSERT, UPDATE, DELETE), store query info and wait for Eloquent event
        if (in_array($queryType, ['INSERT', 'UPDATE', 'DELETE'], true)) {
            $this->storePendingQuery($event, $queryType);
        } else {
            // For other query types (SELECT, etc.), log immediately
            $this->logQueryOnly($event, $queryType);
        }
    }

    /**
     * Handle Eloquent model created event.
     */
    public function created(Model $model): void
    {
        if (!config('laravel-logger.orm.enabled', false)) {
            return;
        }

        $this->handleModelEvent($model, 'INSERT', 'create', 'model_created', null, $model->getAttributes());
    }

    /**
     * Handle Eloquent model updated event.
     */
    public function updated(Model $model): void
    {
        if (!config('laravel-logger.orm.enabled', false)) {
            return;
        }

        $original = $model->getOriginal();
        $afterValue = $model->getAttributes();

        $this->handleModelEvent($model, 'UPDATE', 'update', 'model_updated', $original, $afterValue);
    }

    /**
     * Handle Eloquent model deleted event.
     */
    public function deleted(Model $model): void
    {
        if (!config('laravel-logger.orm.enabled', false)) {
            return;
        }

        $original = $model->getOriginal();

        $this->handleModelEvent($model, 'DELETE', 'delete', 'model_deleted', $original, null);
    }

    /**
     * Handle a model event by combining it with pending query information.
     */
    protected function handleModelEvent(
        Model $model,
        string $queryType,
        string $action,
        string $message,
        ?array $previousValue,
        ?array $afterValue
    ): void {
        // Cleanup old pending queries before processing
        $this->cleanupOldPendingQueries();

        $modelClass = get_class($model);
        $modelId = $model->getKey() ? (string) $model->getKey() : null;
        $table = $model->getTable();
        $connection = $model->getConnectionName() ?: \Illuminate\Support\Facades\DB::getDefaultConnection();

        // Try to find matching pending query
        $pendingQuery = $this->findAndRemovePendingQuery($connection, $table, $queryType, $modelId);

        if ($pendingQuery !== null) {
            // Found matching query - combine information
            $this->logCombined(
                message: $message,
                model: $modelClass,
                modelId: $modelId,
                action: $action,
                query: $pendingQuery['query'],
                queryType: $queryType,
                bindings: $pendingQuery['bindings'],
                durationMs: $pendingQuery['durationMs'],
                isSlowQuery: $pendingQuery['isSlowQuery'],
                connection: $connection,
                table: $table,
                transactionId: $pendingQuery['transactionId'],
                previousValue: $previousValue,
                afterValue: $afterValue,
            );
        } else {
            // No matching query found - log only model event info
            $this->logModelEventOnly(
                message: $message,
                model: $modelClass,
                modelId: $modelId,
                action: $action,
                queryType: $queryType,
                connection: $connection,
                table: $table,
                previousValue: $previousValue,
                afterValue: $afterValue,
            );
        }
    }

    /**
     * Store query information temporarily, waiting for Eloquent event.
     */
    protected function storePendingQuery(QueryExecuted $event, string $queryType): void
    {
        $table = $this->extractTable($event->sql, $queryType);
        $connection = $event->connectionName ?: DB::getDefaultConnection();
        $modelId = $this->extractModelId($event->sql, $queryType, $event->bindings);
        
        $durationMs = (int) round($event->time);
        $slowQueryThreshold = config('laravel-logger.orm.slow_query_threshold_ms', 1000);
        $isSlowQuery = $durationMs >= $slowQueryThreshold;
        
        $transactionId = $this->getTransactionId($connection);
        $bindings = $this->formatBindings($event->bindings);
        
        // Create key for pending query lookup
        // Use connection + table + queryType + modelId (if available) + timestamp for uniqueness
        $key = $this->createPendingQueryKey($connection, $table, $queryType, $modelId);
        
        self::$pendingQueries[$key] = [
            'query' => $event->sql,
            'bindings' => $bindings,
            'durationMs' => $durationMs,
            'isSlowQuery' => $isSlowQuery,
            'transactionId' => $transactionId,
            'timestamp' => microtime(true),
            'connection' => $connection,
            'table' => $table,
            'queryType' => $queryType,
            'modelId' => $modelId,
        ];

        // Cleanup old pending queries periodically
        $this->cleanupOldPendingQueries();
    }

    /**
     * Find and remove a pending query that matches the model event.
     */
    protected function findAndRemovePendingQuery(
        string $connection,
        string $table,
        string $queryType,
        ?string $modelId
    ): ?array {
        // Try exact match first (with modelId) - for UPDATE/DELETE
        if ($modelId !== null && $queryType !== 'INSERT') {
            $key = $this->createPendingQueryKey($connection, $table, $queryType, $modelId);
            if (isset(self::$pendingQueries[$key])) {
                $query = self::$pendingQueries[$key];
                unset(self::$pendingQueries[$key]);
                return $query;
            }
        }

        // For INSERT or when modelId match fails, find the most recent matching query
        // This handles INSERT where ID is generated after the query
        $bestMatch = null;
        $bestMatchKey = null;
        $bestTimestamp = 0;
        
        foreach (self::$pendingQueries as $k => $query) {
            if ($query['connection'] === $connection
                && $query['table'] === $table
                && $query['queryType'] === $queryType
                && $query['timestamp'] > $bestTimestamp
            ) {
                // For UPDATE/DELETE, only match if modelId matches or query doesn't have modelId
                if ($queryType === 'INSERT' 
                    || $modelId === null 
                    || $query['modelId'] === null 
                    || $query['modelId'] === $modelId
                ) {
                    $bestMatch = $query;
                    $bestMatchKey = $k;
                    $bestTimestamp = $query['timestamp'];
                }
            }
        }

        if ($bestMatch !== null && $bestMatchKey !== null) {
            unset(self::$pendingQueries[$bestMatchKey]);
        }

        return $bestMatch;
    }

    /**
     * Create a key for pending query lookup.
     */
    protected function createPendingQueryKey(
        string $connection,
        string $table,
        string $queryType,
        ?string $modelId
    ): string {
        $base = "{$connection}:{$table}:{$queryType}";
        return $modelId !== null ? "{$base}:{$modelId}" : "{$base}:*";
    }

    /**
     * Cleanup old pending queries that haven't been matched.
     */
    protected function cleanupOldPendingQueries(): void
    {
        $now = microtime(true);
        $keysToRemove = [];

        foreach (self::$pendingQueries as $key => $query) {
            $age = $now - $query['timestamp'];
            
            // If query is too old, log it without Eloquent event and remove
            if ($age > self::MAX_PENDING_AGE) {
                $this->logQueryOnlyFromPending($query);
                $keysToRemove[] = $key;
            }
        }

        foreach ($keysToRemove as $key) {
            unset(self::$pendingQueries[$key]);
        }
    }

    /**
     * Log a combined entry with both query and model event information.
     */
    protected function logCombined(
        string $message,
        string $model,
        ?string $modelId,
        string $action,
        string $query,
        string $queryType,
        ?string $bindings,
        int $durationMs,
        bool $isSlowQuery,
        string $connection,
        string $table,
        ?string $transactionId,
        ?array $previousValue,
        ?array $afterValue,
    ): void {
        Log::orm(
            new OrmLogObject(
                message: $message,
                model: $model,
                modelId: $modelId,
                action: $action,
                query: $query,
                queryType: $queryType,
                isSlowQuery: $isSlowQuery,
                durationMs: $durationMs,
                bindings: $bindings,
                connection: $connection,
                table: $table,
                transactionId: $transactionId,
                userId: Auth::id() ? (string) Auth::id() : null,
                previousValue: $previousValue,
                afterValue: $afterValue,
                level: $isSlowQuery ? 'warning' : 'info',
            ),
            defer: true,
        );
    }

    /**
     * Log only query information (for raw queries or when Eloquent event doesn't arrive).
     */
    protected function logQueryOnly(QueryExecuted $event, ?string $queryType): void
    {
        $table = $this->extractTable($event->sql, $queryType);
        $connection = $event->connectionName ?: DB::getDefaultConnection();
        $durationMs = (int) round($event->time);
        $slowQueryThreshold = config('laravel-logger.orm.slow_query_threshold_ms', 1000);
        $isSlowQuery = $durationMs >= $slowQueryThreshold;
        $transactionId = $this->getTransactionId($connection);
        $model = $this->extractModel($event->sql, $table);
        $modelId = $this->extractModelId($event->sql, $queryType, $event->bindings);
        $bindings = $this->formatBindings($event->bindings);

        Log::orm(
            new OrmLogObject(
                message: 'database_query',
                model: $model,
                modelId: $modelId,
                action: $this->mapQueryTypeToAction($queryType),
                query: $event->sql,
                queryType: $queryType,
                isSlowQuery: $isSlowQuery,
                durationMs: $durationMs,
                bindings: $bindings,
                connection: $connection,
                table: $table,
                transactionId: $transactionId,
                userId: Auth::id() ? (string) Auth::id() : null,
                previousValue: null,
                afterValue: null,
                level: $isSlowQuery ? 'warning' : 'info',
            ),
            defer: true,
        );
    }

    /**
     * Log query only from pending query data.
     */
    protected function logQueryOnlyFromPending(array $pendingQuery): void
    {
        $model = $this->extractModel($pendingQuery['query'], $pendingQuery['table']);

        Log::orm(
            new OrmLogObject(
                message: 'database_query',
                model: $model,
                modelId: $pendingQuery['modelId'],
                action: $this->mapQueryTypeToAction($pendingQuery['queryType']),
                query: $pendingQuery['query'],
                queryType: $pendingQuery['queryType'],
                isSlowQuery: $pendingQuery['isSlowQuery'],
                durationMs: $pendingQuery['durationMs'],
                bindings: $pendingQuery['bindings'],
                connection: $pendingQuery['connection'],
                table: $pendingQuery['table'],
                transactionId: $pendingQuery['transactionId'],
                userId: Auth::id() ? (string) Auth::id() : null,
                previousValue: null,
                afterValue: null,
                level: $pendingQuery['isSlowQuery'] ? 'warning' : 'info',
            ),
            defer: true,
        );
    }

    /**
     * Log only model event information (when query info is not available).
     */
    protected function logModelEventOnly(
        string $message,
        string $model,
        ?string $modelId,
        string $action,
        string $queryType,
        string $connection,
        string $table,
        ?array $previousValue,
        ?array $afterValue,
    ): void {
        $transactionId = $this->getTransactionId($connection);

        Log::orm(
            new OrmLogObject(
                message: $message,
                model: $model,
                modelId: $modelId,
                action: $action,
                query: null,
                queryType: $queryType,
                isSlowQuery: null,
                durationMs: null,
                bindings: null,
                connection: $connection,
                table: $table,
                transactionId: $transactionId,
                userId: Auth::id() ? (string) Auth::id() : null,
                previousValue: $previousValue,
                afterValue: $afterValue,
                level: 'info',
            ),
            defer: true,
        );
    }

    /**
     * Check if query should be ignored based on patterns.
     */
    protected function shouldIgnoreQuery(string $sql): bool
    {
        $ignorePatterns = config('laravel-logger.orm.ignore_patterns', [
            'select * from `migrations`',
            'select * from `jobs`',
            'select * from `failed_jobs`',
            'select * from `job_batches`',
        ]);

        $sqlLower = strtolower($sql);

        foreach ($ignorePatterns as $pattern) {
            if (str_contains($sqlLower, strtolower($pattern))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract query type from SQL (SELECT, INSERT, UPDATE, DELETE).
     */
    protected function extractQueryType(string $sql): ?string
    {
        $sqlTrimmed = trim($sql);
        
        if (preg_match('/^\s*(SELECT|INSERT|UPDATE|DELETE|CREATE|DROP|ALTER|TRUNCATE|REPLACE)\s+/i', $sqlTrimmed, $matches)) {
            return strtoupper($matches[1]);
        }

        return null;
    }

    /**
     * Extract table name from SQL query.
     */
    protected function extractTable(string $sql, ?string $queryType): ?string
    {
        $sqlLower = strtolower($sql);

        if (preg_match('/(?:from|into|update|table)\s+[`"]?(\w+)[`"]?/i', $sqlLower, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Extract model class name from table name (Laravel convention).
     */
    protected function extractModel(string $sql, ?string $table): ?string
    {
        if ($table === null) {
            return null;
        }

        $modelName = Str::singular(Str::studly($table));

        $possibleNamespaces = [
            'App\\Models\\',
            'App\\',
        ];

        foreach ($possibleNamespaces as $namespace) {
            $className = $namespace . $modelName;
            if (class_exists($className) && is_subclass_of($className, Model::class)) {
                return $className;
            }
        }

        return null;
    }

    /**
     * Extract model ID from query bindings (for UPDATE/DELETE queries).
     */
    protected function extractModelId(string $sql, ?string $queryType, array $bindings): ?string
    {
        if (!in_array($queryType, ['UPDATE', 'DELETE', 'SELECT'], true)) {
            return null;
        }

        if (preg_match('/where\s+[`"]?id[`"]?\s*=\s*\?/i', $sql, $matches)) {
            if (preg_match_all('/\?/', $sql, $matches, PREG_OFFSET_CAPTURE)) {
                $questionMarkPositions = array_column($matches[0], 1);
                $idPosition = null;
                
                foreach ($questionMarkPositions as $index => $pos) {
                    $before = substr($sql, max(0, $pos - 20), 20);
                    if (preg_match('/[`"]?id[`"]?\s*=\s*$/', $before)) {
                        $idPosition = $index;
                        break;
                    }
                }
                
                if ($idPosition !== null && isset($bindings[$idPosition])) {
                    return (string) $bindings[$idPosition];
                }
            }
        }

        return null;
    }

    /**
     * Map query type to action name.
     */
    protected function mapQueryTypeToAction(?string $queryType): ?string
    {
        return match ($queryType) {
            'SELECT' => 'read',
            'INSERT' => 'create',
            'UPDATE' => 'update',
            'DELETE' => 'delete',
            default => strtolower($queryType ?? 'unknown'),
        };
    }

    /**
     * Format bindings for logging.
     */
    protected function formatBindings(array $bindings): ?string
    {
        if (empty($bindings)) {
            return null;
        }

        $formatted = json_encode($bindings);
        
        $maxSize = ConfigReader::getInt('limits.max_bindings_size', 2048);
        if (strlen($formatted) > $maxSize) {
            return substr($formatted, 0, $maxSize) . '...[truncated]';
        }

        return $formatted;
    }

    /**
     * Transaction ID storage per connection.
     * Structure: ['connection:level' => ['id' => 'txn-...', 'timestamp' => 1234567890]]
     * 
     * @var array<string, array{id: string, timestamp: int}>
     */
    protected static array $transactionIds = [];

    /**
     * Maximum age for transaction IDs before cleanup (1 hour).
     */
    private const MAX_TRANSACTION_AGE = 3600;

    /**
     * Get transaction ID if within a transaction.
     */
    protected function getTransactionId(?string $connectionName): ?string
    {
        $connectionName = $connectionName ?: DB::getDefaultConnection();

        try {
            $connection = DB::connection($connectionName);
            $transactionLevel = $connection->transactionLevel();

            if ($transactionLevel === 0) {
                $defaultLevel = DB::transactionLevel();
                if ($defaultLevel > 0) {
                    $transactionLevel = $defaultLevel;
                    $connectionName = DB::getDefaultConnection();
                }
            }
            
            $this->cleanupOldTransactions($connectionName, $transactionLevel);
            
            if ($transactionLevel > 0) {
                $key = $connectionName . ':' . $transactionLevel;
                
                if (!isset(self::$transactionIds[$key])) {
                    self::$transactionIds[$key] = [
                        'id' => 'txn-' . Str::uuid()->toString(),
                        'timestamp' => time(),
                    ];
                }
                
                return self::$transactionIds[$key]['id'];
            } else {
                $this->clearTransactionIdsForConnection($connectionName);
            }
        } catch (\Throwable $e) {
            // Ignore errors
        }

        return null;
    }

    /**
     * Cleanup old transaction IDs to prevent memory leaks.
     */
    private function cleanupOldTransactions(string $connectionName, int $currentLevel): void
    {
        $now = time();
        $keysToRemove = [];

        foreach (self::$transactionIds as $key => $data) {
            if (!str_starts_with($key, $connectionName . ':')) {
                continue;
            }

            $parts = explode(':', $key, 2);
            $storedLevel = (int) $parts[1];

            if (isset($data['timestamp']) && ($now - $data['timestamp']) > self::MAX_TRANSACTION_AGE) {
                $keysToRemove[] = $key;
                continue;
            }

            if ($storedLevel > $currentLevel) {
                $keysToRemove[] = $key;
            }
        }

        foreach ($keysToRemove as $key) {
            unset(self::$transactionIds[$key]);
        }
    }

    /**
     * Clear all transaction IDs for a specific connection.
     */
    private function clearTransactionIdsForConnection(string $connectionName): void
    {
        $keysToRemove = [];
        
        foreach (self::$transactionIds as $key => $data) {
            if (str_starts_with($key, $connectionName . ':')) {
                $keysToRemove[] = $key;
            }
        }

        foreach ($keysToRemove as $key) {
            unset(self::$transactionIds[$key]);
        }
    }
}
