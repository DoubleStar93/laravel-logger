<?php

namespace Ermetix\LaravelLogger\Listeners;

use Ermetix\LaravelLogger\Facades\LaravelLogger as Log;
use Ermetix\LaravelLogger\Support\Config\ConfigReader;
use Ermetix\LaravelLogger\Support\Logging\Objects\OrmLogObject;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Automatically log database queries to orm_log index.
 * 
 * This listener intercepts all database queries and logs them with:
 * - Query SQL and bindings
 * - Duration
 * - Connection and table
 * - Query type (SELECT, INSERT, UPDATE, DELETE)
 * - Slow query detection
 * - Transaction ID (if within a transaction)
 */
class LogDatabaseQuery
{
    /**
     * Handle the query executed event.
     */
    public function handle(QueryExecuted $event): void
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
        // By default, only log write operations (INSERT, UPDATE, DELETE)
        if ($queryType === 'SELECT' && !config('laravel-logger.orm.log_read_operations', false)) {
            return;
        }
        
        $table = $this->extractTable($event->sql, $queryType);
        $durationMs = (int) round($event->time);
        $slowQueryThreshold = config('laravel-logger.orm.slow_query_threshold_ms', 1000);
        $isSlowQuery = $durationMs >= $slowQueryThreshold;

        // Get transaction ID if within a transaction
        $transactionId = $this->getTransactionId($event->connectionName);

        // Extract model information from query (if possible)
        $model = $this->extractModel($event->sql, $table);
        $modelId = $this->extractModelId($event->sql, $queryType, $event->bindings);

        // Format bindings for logging
        $bindings = $this->formatBindings($event->bindings);

        // Create log entry
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
                connection: $event->connectionName,
                table: $table,
                transactionId: $transactionId,
                userId: Auth::id() ? (string) Auth::id() : null,
                level: $isSlowQuery ? 'warning' : 'info',
            ),
            defer: true, // Defer to end of request
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

        // Pattern: FROM `table_name` or INTO `table_name` or UPDATE `table_name`
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

        // Try to find model class from table name
        // Common Laravel convention: users -> User, user_profiles -> UserProfile
        $modelName = Str::singular(Str::studly($table));

        // Check if model class exists
        $possibleNamespaces = [
            'App\\Models\\',
            'App\\',
        ];

        foreach ($possibleNamespaces as $namespace) {
            $className = $namespace . $modelName;
            if (class_exists($className) && is_subclass_of($className, \Illuminate\Database\Eloquent\Model::class)) {
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
        if (!in_array($queryType, ['UPDATE', 'DELETE', 'SELECT'])) {
            return null;
        }

        // Pattern: WHERE `id` = ? or WHERE id = ?
        if (preg_match('/where\s+[`"]?id[`"]?\s*=\s*\?/i', $sql, $matches)) {
            // Try to get the actual ID value from bindings
            // Find the position of the ? in WHERE id = ?
            if (preg_match_all('/\?/', $sql, $matches, PREG_OFFSET_CAPTURE)) {
                $questionMarkPositions = array_column($matches[0], 1);
                $idPosition = null;
                
                // Find which ? corresponds to the id condition
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

        // Limit bindings size to configured maximum to prevent huge logs
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
     * 
     * Uses a static cache to maintain the same transaction ID for all queries
     * within the same transaction level. Automatically cleans up old transaction IDs
     * to prevent memory leaks.
     */
    protected function getTransactionId(?string $connectionName): ?string
    {
        $connectionName = $connectionName ?: DB::getDefaultConnection();

        try {
            $connection = DB::connection($connectionName);
            $transactionLevel = $connection->transactionLevel();

            // Defensive fallback: in some environments the transaction is started on the default
            // connection facade, but the provided connection name might not reflect the same instance.
            if ($transactionLevel === 0) {
                $defaultLevel = DB::transactionLevel();
                if ($defaultLevel > 0) {
                    $transactionLevel = $defaultLevel;
                    $connectionName = DB::getDefaultConnection();
                }
            }
            
            // Cleanup old transaction IDs periodically to prevent memory leaks
            // This removes stale transactions and transactions for levels that are no longer active
            $this->cleanupOldTransactions($connectionName, $transactionLevel);
            
            // Check if we're in a transaction
            if ($transactionLevel > 0) {
                $key = $connectionName . ':' . $transactionLevel;
                
                // Generate and cache transaction ID for this connection/level
                if (!isset(self::$transactionIds[$key])) {
                    self::$transactionIds[$key] = [
                        'id' => 'txn-' . Str::uuid()->toString(),
                        'timestamp' => time(),
                    ];
                }
                
                return self::$transactionIds[$key]['id'];
            } else {
                // Clear all transaction IDs for this connection when not in transaction
                // This handles the case where transaction level goes from N to 0
                // Also ensures cleanup happens even if no queries are executed after commit/rollback
                $this->clearTransactionIdsForConnection($connectionName);
            }
        } catch (\Throwable $e) {
            // Ignore errors
        }

        return null;
    }

    /**
     * Cleanup old transaction IDs to prevent memory leaks.
     * Removes transaction IDs older than MAX_TRANSACTION_AGE and cleans up
     * transaction IDs for levels that are no longer active.
     * 
     * This method is called on every query to ensure stale transaction IDs
     * are cleaned up promptly, preventing memory leaks in long-running processes.
     * 
     * @param string $connectionName
     * @param int $currentLevel Current transaction level
     */
    private function cleanupOldTransactions(string $connectionName, int $currentLevel): void
    {
        $now = time();
        $keysToRemove = [];

        foreach (self::$transactionIds as $key => $data) {
            // Check if this is for the same connection
            if (!str_starts_with($key, $connectionName . ':')) {
                continue;
            }

            // Extract level from key (format: "connection:level")
            // Since we already checked str_starts_with($key, $connectionName . ':'),
            // the key must contain ':', so explode will always return 2 parts
            $parts = explode(':', $key, 2);
            $storedLevel = (int) $parts[1];

            // Remove if transaction is too old (stale transaction from crashed app)
            // This prevents memory leaks from transactions that were never properly closed
            if (isset($data['timestamp']) && ($now - $data['timestamp']) > self::MAX_TRANSACTION_AGE) {
                $keysToRemove[] = $key;
                continue;
            }

            // Remove if stored level is higher than current level (transaction was rolled back/committed)
            // This handles nested transactions: if we're at level 1, remove level 2, 3, etc.
            // For example: if we had nested transactions at levels 1, 2, 3 and we commit level 3,
            // we're now at level 2, so we remove level 3. If we commit level 2, we're at level 1,
            // so we remove levels 2 and 3.
            if ($storedLevel > $currentLevel) {
                $keysToRemove[] = $key;
            }
        }

        // Remove cleaned up keys
        foreach ($keysToRemove as $key) {
            unset(self::$transactionIds[$key]);
        }
    }

    /**
     * Clear all transaction IDs for a specific connection.
     * Called when transaction level reaches 0.
     * 
     * @param string $connectionName
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
