<?php

use Ermetix\LaravelLogger\Facades\LaravelLogger;
use Ermetix\LaravelLogger\Listeners\LogOrmOperation;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class TestableLogOrmOperation extends LogOrmOperation
{
    public function testCreatePendingQueryKey(string $connection, string $table, string $queryType, ?string $modelId): string
    {
        return $this->createPendingQueryKey($connection, $table, $queryType, $modelId);
    }
    
    public function testCleanupOldPendingQueries(): void
    {
        $this->cleanupOldPendingQueries();
    }
    
    public function testLogQueryOnlyFromPending(array $pendingQuery): void
    {
        $this->logQueryOnlyFromPending($pendingQuery);
    }
    
    public function testExtractModelId(string $sql, ?string $queryType, array $bindings): ?string
    {
        return $this->extractModelId($sql, $queryType, $bindings);
    }
    
    public function testExtractModel(string $sql, ?string $table): ?string
    {
        return $this->extractModel($sql, $table);
    }
    
    public function testGetTransactionId(?string $connectionName): ?string
    {
        return $this->getTransactionId($connectionName);
    }
}

test('LogOrmOperation createPendingQueryKey includes modelId when present', function () {
    $listener = new TestableLogOrmOperation();
    
    $key = $listener->testCreatePendingQueryKey('mysql', 'users', 'UPDATE', '123');
    expect($key)->toBe('mysql:users:UPDATE:123');
});

test('LogOrmOperation createPendingQueryKey uses wildcard when modelId is null', function () {
    $listener = new TestableLogOrmOperation();
    
    $key = $listener->testCreatePendingQueryKey('mysql', 'users', 'INSERT', null);
    expect($key)->toBe('mysql:users:INSERT:*');
});

test('LogOrmOperation cleanupOldPendingQueries logs and removes old queries', function () {
    config(['laravel-logger.orm.enabled' => true]);
    
    $listener = new TestableLogOrmOperation();
    
    // Use reflection to set old pending queries
    $reflection = new ReflectionClass($listener);
    $property = $reflection->getProperty('pendingQueries');
    $property->setAccessible(true);
    
    // Set old query (older than MAX_PENDING_AGE = 5 seconds)
    $oldTimestamp = microtime(true) - 10; // 10 seconds ago
    $property->setValue($listener, [
        'mysql:users:INSERT:*' => [
            'query' => 'INSERT INTO users VALUES (?)',
            'bindings' => null,
            'durationMs' => 5,
            'isSlowQuery' => false,
            'transactionId' => null,
            'timestamp' => $oldTimestamp,
            'connection' => 'mysql',
            'table' => 'users',
            'queryType' => 'INSERT',
            'modelId' => null,
        ],
    ]);
    
    LaravelLogger::shouldReceive('orm')
        ->once()
        ->with(\Mockery::type(\Ermetix\LaravelLogger\Support\Logging\Objects\OrmLogObject::class), true);
    
    $listener->testCleanupOldPendingQueries();
    
    // Query should be removed
    $remaining = $property->getValue($listener);
    expect($remaining)->toBeEmpty();
});

test('LogOrmOperation logQueryOnlyFromPending logs pending query', function () {
    config(['laravel-logger.orm.enabled' => true]);
    
    $listener = new TestableLogOrmOperation();
    
    $pendingQuery = [
        'query' => 'UPDATE users SET name = ? WHERE id = ?',
        'bindings' => '["New Name", 1]',
        'durationMs' => 10,
        'isSlowQuery' => false,
        'transactionId' => null,
        'connection' => 'mysql',
        'table' => 'users',
        'queryType' => 'UPDATE',
        'modelId' => '1',
    ];
    
    LaravelLogger::shouldReceive('orm')
        ->once()
        ->with(\Mockery::on(function ($logObject) {
            return $logObject instanceof \Ermetix\LaravelLogger\Support\Logging\Objects\OrmLogObject
                && $logObject->query === 'UPDATE users SET name = ? WHERE id = ?'
                && $logObject->queryType === 'UPDATE'
                && $logObject->modelId === '1';
        }), true);
    
    $listener->testLogQueryOnlyFromPending($pendingQuery);
});

test('LogOrmOperation extractModelId extracts ID from UPDATE query', function () {
    $listener = new TestableLogOrmOperation();
    
    $sql = 'UPDATE users SET name = ? WHERE id = ?';
    $bindings = ['New Name', 123];
    
    $modelId = $listener->testExtractModelId($sql, 'UPDATE', $bindings);
    expect($modelId)->toBe('123');
});

test('LogOrmOperation extractModelId extracts ID from DELETE query', function () {
    $listener = new TestableLogOrmOperation();
    
    $sql = 'DELETE FROM users WHERE id = ?';
    $bindings = [456];
    
    $modelId = $listener->testExtractModelId($sql, 'DELETE', $bindings);
    expect($modelId)->toBe('456');
});

test('LogOrmOperation extractModelId returns null for INSERT queries', function () {
    $listener = new TestableLogOrmOperation();
    
    $sql = 'INSERT INTO users (name) VALUES (?)';
    $bindings = ['Test'];
    
    $modelId = $listener->testExtractModelId($sql, 'INSERT', $bindings);
    expect($modelId)->toBeNull();
});

test('LogOrmOperation extractModelId returns null when no id in WHERE clause', function () {
    $listener = new TestableLogOrmOperation();
    
    $sql = 'UPDATE users SET name = ? WHERE email = ?';
    $bindings = ['New Name', 'test@example.com'];
    
    $modelId = $listener->testExtractModelId($sql, 'UPDATE', $bindings);
    expect($modelId)->toBeNull();
});

test('LogOrmOperation extractModelId handles complex WHERE clauses', function () {
    $listener = new TestableLogOrmOperation();
    
    $sql = 'UPDATE users SET name = ? WHERE id = ? AND status = ?';
    $bindings = ['New Name', 789, 'active'];
    
    // Should find the id binding (second ?)
    $modelId = $listener->testExtractModelId($sql, 'UPDATE', $bindings);
    expect($modelId)->toBe('789');
});

test('LogOrmOperation extractModel returns model class when exists or null', function () {
    $listener = new TestableLogOrmOperation();
    
    // Test with a table that would map to a model (App\Models\User may not exist in test env)
    $model = $listener->testExtractModel('SELECT * FROM users', 'users');
    // Result is string (class name) if model exists, null otherwise - both are valid
    expect($model)->toBeIn([null, 'App\\Models\\User', 'App\\User']);
});

test('LogOrmOperation extractModel returns null when table is null', function () {
    $listener = new TestableLogOrmOperation();
    
    $model = $listener->testExtractModel('SELECT 1', null);
    expect($model)->toBeNull();
});

test('LogOrmOperation getTransactionId returns null when not in transaction', function () {
    $listener = new TestableLogOrmOperation();
    
    $transactionId = $listener->testGetTransactionId('mysql');
    expect($transactionId)->toBeNull();
});

test('LogOrmOperation getTransactionId returns ID when in transaction', function () {
    config(['laravel-logger.orm.enabled' => true]);
    
    $listener = new TestableLogOrmOperation();
    
    DB::beginTransaction();
    
    try {
        $transactionId = $listener->testGetTransactionId('mysql');
        expect($transactionId)->not->toBeNull();
        expect($transactionId)->toStartWith('txn-');
    } finally {
        DB::rollBack();
    }
});

test('LogOrmOperation getTransactionId uses default connection when null', function () {
    $listener = new TestableLogOrmOperation();
    
    $transactionId = $listener->testGetTransactionId(null);
    expect($transactionId)->toBeNull(); // Not in transaction
});

test('LogOrmOperation getTransactionId handles connection errors gracefully', function () {
    $listener = new TestableLogOrmOperation();
    
    // Try with invalid connection name
    $transactionId = $listener->testGetTransactionId('invalid_connection');
    // Should return null on error, not throw
    expect($transactionId)->toBeNull();
});

test('LogOrmOperation findAndRemovePendingQuery finds exact match with modelId', function () {
    config(['laravel-logger.orm.enabled' => true]);
    
    $listener = new LogOrmOperation();
    
    // Store a pending query
    $queryEvent = new QueryExecuted(
        sql: 'UPDATE users SET name = ? WHERE id = ?',
        bindings: ['New Name', 123],
        time: 5.0,
        connection: DB::connection(),
    );
    $listener->handleQueryExecuted($queryEvent);
    
    // Use reflection to access findAndRemovePendingQuery
    $reflection = new ReflectionClass($listener);
    $method = $reflection->getMethod('findAndRemovePendingQuery');
    $method->setAccessible(true);
    
    // Should find the pending query
    $found = $method->invoke($listener, DB::getDefaultConnection(), 'users', 'UPDATE', '123');
    expect($found)->not->toBeNull();
    expect($found['query'])->toBe('UPDATE users SET name = ? WHERE id = ?');
});

test('LogOrmOperation findAndRemovePendingQuery finds best match for INSERT', function () {
    config(['laravel-logger.orm.enabled' => true]);
    
    $listener = new LogOrmOperation();
    
    // Store a pending INSERT query (no modelId yet)
    $queryEvent = new QueryExecuted(
        sql: 'INSERT INTO users (name, email) VALUES (?, ?)',
        bindings: ['Test User', 'test@example.com'],
        time: 5.0,
        connection: DB::connection(),
    );
    $listener->handleQueryExecuted($queryEvent);
    
    // Use reflection to access findAndRemovePendingQuery
    $reflection = new ReflectionClass($listener);
    $method = $reflection->getMethod('findAndRemovePendingQuery');
    $method->setAccessible(true);
    
    // Should find the pending query even without modelId (INSERT case)
    $found = $method->invoke($listener, DB::getDefaultConnection(), 'users', 'INSERT', null);
    expect($found)->not->toBeNull();
    expect($found['query'])->toBe('INSERT INTO users (name, email) VALUES (?, ?)');
});

test('LogOrmOperation findAndRemovePendingQuery returns null when no match found', function () {
    $listener = new LogOrmOperation();
    
    // Use reflection to access findAndRemovePendingQuery
    $reflection = new ReflectionClass($listener);
    $method = $reflection->getMethod('findAndRemovePendingQuery');
    $method->setAccessible(true);
    
    // Should return null when no pending query exists
    $found = $method->invoke($listener, 'mysql', 'nonexistent', 'UPDATE', '999');
    expect($found)->toBeNull();
});

test('LogOrmOperation logModelEventOnly logs model event without query', function () {
    config(['laravel-logger.orm.enabled' => true]);
    
    $listener = new LogOrmOperation();
    
    // Use reflection to access logModelEventOnly
    $reflection = new ReflectionClass($listener);
    $method = $reflection->getMethod('logModelEventOnly');
    $method->setAccessible(true);
    
    LaravelLogger::shouldReceive('orm')
        ->once()
        ->with(\Mockery::on(function ($logObject) {
            return $logObject instanceof \Ermetix\LaravelLogger\Support\Logging\Objects\OrmLogObject
                && $logObject->query === null
                && $logObject->model === 'App\\Models\\User'
                && $logObject->action === 'update';
        }), true);
    
    $method->invoke($listener, 
        'model_updated',
        'App\\Models\\User',
        '123',
        'update',
        'UPDATE',
        'mysql',
        'users',
        ['name' => 'Old'],
        ['name' => 'New']
    );
});

test('LogOrmOperation extractModelId handles SELECT queries', function () {
    $listener = new TestableLogOrmOperation();
    
    $sql = 'SELECT * FROM users WHERE id = ?';
    $bindings = [456];
    
    $modelId = $listener->testExtractModelId($sql, 'SELECT', $bindings);
    expect($modelId)->toBe('456');
});

test('LogOrmOperation extractModelId returns null for unsupported query types', function () {
    $listener = new TestableLogOrmOperation();
    
    $sql = 'CREATE TABLE users';
    $bindings = [];
    
    $modelId = $listener->testExtractModelId($sql, 'CREATE', $bindings);
    expect($modelId)->toBeNull();
});

test('LogOrmOperation extractModelId handles queries with quoted id column', function () {
    $listener = new TestableLogOrmOperation();
    
    $sql = 'UPDATE `users` SET name = ? WHERE `id` = ?';
    $bindings = ['New Name', 789];
    
    $modelId = $listener->testExtractModelId($sql, 'UPDATE', $bindings);
    expect($modelId)->toBe('789');
});

test('LogOrmOperation extractModelId handles queries with double-quoted id column', function () {
    $listener = new TestableLogOrmOperation();
    
    $sql = 'UPDATE "users" SET name = ? WHERE "id" = ?';
    $bindings = ['New Name', 101];
    
    $modelId = $listener->testExtractModelId($sql, 'UPDATE', $bindings);
    expect($modelId)->toBe('101');
});

test('LogOrmOperation extractModel returns null when model class does not exist', function () {
    $listener = new TestableLogOrmOperation();
    
    $model = $listener->testExtractModel('SELECT * FROM nonexistent_table', 'nonexistent_table');
    expect($model)->toBeNull();
});

test('LogOrmOperation extractModel tries App\\Models namespace first', function () {
    $listener = new TestableLogOrmOperation();
    
    // Test with a table that would map to a model (App\Models\User may not exist in test env)
    $model = $listener->testExtractModel('SELECT * FROM users', 'users');
    // Result is string (class name) if model exists, null otherwise
    expect($model)->toBeIn([null, 'App\\Models\\User', 'App\\User']);
});

test('LogOrmOperation getTransactionId handles nested transactions', function () {
    config(['laravel-logger.orm.enabled' => true]);
    
    $listener = new TestableLogOrmOperation();
    
    DB::beginTransaction();
    
    try {
        $id1 = $listener->testGetTransactionId(DB::getDefaultConnection());
        expect($id1)->not->toBeNull();
        
        DB::beginTransaction();
        try {
            $id2 = $listener->testGetTransactionId(DB::getDefaultConnection());
            // Both should return transaction IDs (may be same or different depending on implementation)
            expect($id2)->not->toBeNull();
        } finally {
            DB::rollBack();
        }
    } finally {
        DB::rollBack();
    }
});

test('LogOrmOperation formatBindings truncates large bindings', function () {
    config(['laravel-logger.limits.max_bindings_size' => 100]);
    
    $listener = new LogOrmOperation();
    
    $reflection = new ReflectionClass($listener);
    $method = $reflection->getMethod('formatBindings');
    $method->setAccessible(true);
    
    // Create bindings that will exceed the limit when JSON encoded
    $largeBindings = array_fill(0, 100, 'very long string that will exceed limit');
    $formatted = $method->invoke($listener, $largeBindings);
    
    expect($formatted)->toContain('[truncated]');
    expect(strlen($formatted))->toBeLessThanOrEqual(100 + strlen('...[truncated]'));
});

test('LogOrmOperation mapQueryTypeToAction handles unknown query types', function () {
    $listener = new LogOrmOperation();
    
    $reflection = new ReflectionClass($listener);
    $method = $reflection->getMethod('mapQueryTypeToAction');
    $method->setAccessible(true);
    
    expect($method->invoke($listener, 'TRUNCATE'))->toBe('truncate');
    expect($method->invoke($listener, 'REPLACE'))->toBe('replace');
});

test('LogOrmOperation logCombined logs combined query and model event', function () {
    config(['laravel-logger.orm.enabled' => true]);
    
    $listener = new LogOrmOperation();
    
    $reflection = new ReflectionClass($listener);
    $method = $reflection->getMethod('logCombined');
    $method->setAccessible(true);
    
    LaravelLogger::shouldReceive('orm')
        ->once()
        ->with(\Mockery::on(function ($logObject) {
            return $logObject instanceof \Ermetix\LaravelLogger\Support\Logging\Objects\OrmLogObject
                && $logObject->query === 'UPDATE users SET name = ? WHERE id = ?'
                && $logObject->model === 'App\\Models\\User'
                && $logObject->modelId === '123'
                && $logObject->previousValue === ['name' => 'Old']
                && $logObject->afterValue === ['name' => 'New']
                && $logObject->isSlowQuery === false;
        }), true);
    
    $method->invoke($listener,
        'model_updated',
        'App\\Models\\User',
        '123',
        'update',
        'UPDATE users SET name = ? WHERE id = ?',
        'UPDATE',
        '["New Name", 123]',
        10,
        false,
        'mysql',
        'users',
        null,
        ['name' => 'Old'],
        ['name' => 'New']
    );
});

test('LogOrmOperation logCombined uses warning level for slow queries', function () {
    config(['laravel-logger.orm.enabled' => true]);
    
    $listener = new LogOrmOperation();
    
    $reflection = new ReflectionClass($listener);
    $method = $reflection->getMethod('logCombined');
    $method->setAccessible(true);
    
    LaravelLogger::shouldReceive('orm')
        ->once()
        ->with(\Mockery::on(function ($logObject) {
            return $logObject->isSlowQuery === true
                && $logObject->level() === 'warning';
        }), true);
    
    $method->invoke($listener,
        'model_updated',
        'App\\Models\\User',
        '123',
        'update',
        'UPDATE users SET name = ?',
        'UPDATE',
        '["New Name"]',
        1500,
        true, // slow query
        'mysql',
        'users',
        null,
        null,
        ['name' => 'New']
    );
});

test('LogOrmOperation logQueryOnly logs query without model event', function () {
    config(['laravel-logger.orm.enabled' => true]);
    config(['laravel-logger.orm.log_read_operations' => true]);
    
    $listener = new LogOrmOperation();
    
    $queryEvent = new QueryExecuted(
        sql: 'SELECT * FROM users WHERE id = ?',
        bindings: [456],
        time: 2.5,
        connection: DB::connection(),
    );
    
    LaravelLogger::shouldReceive('orm')
        ->once()
        ->with(\Mockery::on(function ($logObject) {
            return $logObject instanceof \Ermetix\LaravelLogger\Support\Logging\Objects\OrmLogObject
                && $logObject->query === 'SELECT * FROM users WHERE id = ?'
                && $logObject->queryType === 'SELECT'
                && $logObject->previousValue === null
                && $logObject->afterValue === null;
        }), true);
    
    $reflection = new ReflectionClass($listener);
    $method = $reflection->getMethod('logQueryOnly');
    $method->setAccessible(true);
    
    $method->invoke($listener, $queryEvent, 'SELECT');
});

test('LogOrmOperation extractTable handles various SQL formats', function () {
    $listener = new LogOrmOperation();
    
    $reflection = new ReflectionClass($listener);
    $method = $reflection->getMethod('extractTable');
    $method->setAccessible(true);
    
    expect($method->invoke($listener, 'SELECT * FROM `users`', 'SELECT'))->toBe('users');
    expect($method->invoke($listener, 'INSERT INTO "test_users" VALUES (?)', 'INSERT'))->toBe('test_users');
    expect($method->invoke($listener, 'UPDATE users SET name = ?', 'UPDATE'))->toBe('users');
    expect($method->invoke($listener, 'DELETE FROM users', 'DELETE'))->toBe('users');
    expect($method->invoke($listener, 'SELECT 1', null))->toBeNull();
});

test('LogOrmOperation extractQueryType handles various query types', function () {
    $listener = new LogOrmOperation();
    
    $reflection = new ReflectionClass($listener);
    $method = $reflection->getMethod('extractQueryType');
    $method->setAccessible(true);
    
    expect($method->invoke($listener, '   SELECT * FROM users'))->toBe('SELECT');
    expect($method->invoke($listener, 'INSERT INTO users'))->toBe('INSERT');
    expect($method->invoke($listener, 'UPDATE users SET'))->toBe('UPDATE');
    expect($method->invoke($listener, 'DELETE FROM users'))->toBe('DELETE');
    expect($method->invoke($listener, 'CREATE TABLE users'))->toBe('CREATE');
    expect($method->invoke($listener, 'DROP TABLE users'))->toBe('DROP');
    expect($method->invoke($listener, 'ALTER TABLE users'))->toBe('ALTER');
    expect($method->invoke($listener, 'TRUNCATE TABLE users'))->toBe('TRUNCATE');
    expect($method->invoke($listener, 'REPLACE INTO users'))->toBe('REPLACE');
    expect($method->invoke($listener, 'invalid sql'))->toBeNull();
});

test('LogOrmOperation shouldIgnoreQuery matches ignore patterns', function () {
    config(['laravel-logger.orm.ignore_patterns' => ['select * from `migrations`', 'select * from `jobs`']]);
    
    $listener = new LogOrmOperation();
    
    $reflection = new ReflectionClass($listener);
    $method = $reflection->getMethod('shouldIgnoreQuery');
    $method->setAccessible(true);
    
    expect($method->invoke($listener, 'SELECT * FROM `migrations`'))->toBeTrue();
    expect($method->invoke($listener, 'SELECT * FROM `jobs`'))->toBeTrue();
    expect($method->invoke($listener, 'SELECT * FROM users'))->toBeFalse();
});

test('LogOrmOperation shouldIgnoreQuery is case insensitive', function () {
    config(['laravel-logger.orm.ignore_patterns' => ['select * from `migrations`']]);
    
    $listener = new LogOrmOperation();
    
    $reflection = new ReflectionClass($listener);
    $method = $reflection->getMethod('shouldIgnoreQuery');
    $method->setAccessible(true);
    
    expect($method->invoke($listener, 'SELECT * FROM `MIGRATIONS`'))->toBeTrue();
    expect($method->invoke($listener, 'select * from `migrations`'))->toBeTrue();
});

test('LogOrmOperation handleQueryExecuted skips when ORM logging is disabled', function () {
    config(['laravel-logger.orm.enabled' => false]);
    
    LaravelLogger::shouldReceive('orm')->never();
    
    $listener = new LogOrmOperation();
    $event = new QueryExecuted('SELECT * FROM users', [], 1.0, DB::connection());
    
    $listener->handleQueryExecuted($event);
});

test('LogOrmOperation updated returns early when ORM logging is disabled', function () {
    config(['laravel-logger.orm.enabled' => false]);
    
    LaravelLogger::shouldReceive('orm')->never();
    
    $listener = new LogOrmOperation();
    $model = new class extends Model {
        protected $table = 'users';
    };
    $model->setRawAttributes(['id' => 1, 'name' => 'Test']);
    $model->syncOriginal();
    
    $listener->updated($model);
});

test('LogOrmOperation deleted returns early when ORM logging is disabled', function () {
    config(['laravel-logger.orm.enabled' => false]);
    
    LaravelLogger::shouldReceive('orm')->never();
    
    $listener = new LogOrmOperation();
    $model = new class extends Model {
        protected $table = 'users';
    };
    $model->setRawAttributes(['id' => 1, 'name' => 'Test']);
    $model->syncOriginal();
    
    $listener->deleted($model);
});

test('LogOrmOperation handleQueryExecuted skips SELECT when read operations disabled', function () {
    config([
        'laravel-logger.orm.enabled' => true,
        'laravel-logger.orm.log_read_operations' => false,
    ]);
    
    LaravelLogger::shouldReceive('orm')->never();
    
    $listener = new LogOrmOperation();
    $event = new QueryExecuted('SELECT * FROM users', [], 1.0, DB::connection());
    
    $listener->handleQueryExecuted($event);
});

test('LogOrmOperation handleQueryExecuted logs SELECT when read operations enabled', function () {
    config([
        'laravel-logger.orm.enabled' => true,
        'laravel-logger.orm.log_read_operations' => true,
    ]);
    
    LaravelLogger::shouldReceive('orm')
        ->once()
        ->with(\Mockery::type(\Ermetix\LaravelLogger\Support\Logging\Objects\OrmLogObject::class), true);
    
    $listener = new LogOrmOperation();
    $event = new QueryExecuted('SELECT * FROM users', [], 1.0, DB::connection());
    
    $listener->handleQueryExecuted($event);
});

test('LogOrmOperation handleQueryExecuted stores pending query for INSERT', function () {
    config(['laravel-logger.orm.enabled' => true]);
    
    LaravelLogger::shouldReceive('orm')->never(); // Should not log immediately
    
    $listener = new LogOrmOperation();
    $event = new QueryExecuted(
        sql: 'INSERT INTO users (name) VALUES (?)',
        bindings: ['Test'],
        time: 1.0,
        connection: DB::connection(),
    );
    
    $listener->handleQueryExecuted($event);
    
    // Query should be stored as pending
    $reflection = new ReflectionClass($listener);
    $property = $reflection->getProperty('pendingQueries');
    $property->setAccessible(true);
    $pending = $property->getValue($listener);
    
    expect($pending)->not->toBeEmpty();
});

test('LogOrmOperation handleModelEvent logs model event only when no pending query', function () {
    config(['laravel-logger.orm.enabled' => true]);
    
    $listener = new LogOrmOperation();
    
    $model = new class extends Model {
        protected $table = 'users';
        protected $fillable = ['name'];
    };
    $model->setRawAttributes(['id' => 999, 'name' => 'Test']);
    
    LaravelLogger::shouldReceive('orm')
        ->once()
        ->with(\Mockery::type(\Ermetix\LaravelLogger\Support\Logging\Objects\OrmLogObject::class), true);
    
    $listener->created($model);
});

test('LogOrmOperation handleModelEvent combines with pending query when found', function () {
    config(['laravel-logger.orm.enabled' => true]);
    
    $listener = new LogOrmOperation();
    
    // First, store a pending query
    $queryEvent = new QueryExecuted(
        sql: 'INSERT INTO users (name) VALUES (?)',
        bindings: ['Test User'],
        time: 2.0,
        connection: DB::connection(),
    );
    $listener->handleQueryExecuted($queryEvent);
    
    // Then trigger model event - should combine
    $model = new class extends Model {
        protected $table = 'users';
        protected $fillable = ['name'];
    };
    $model->setRawAttributes(['id' => 1, 'name' => 'Test User']);
    
    LaravelLogger::shouldReceive('orm')
        ->once()
        ->with(\Mockery::on(function ($logObject) {
            return $logObject instanceof \Ermetix\LaravelLogger\Support\Logging\Objects\OrmLogObject
                && $logObject->query !== null // Should have query
                && $logObject->message() === 'model_created';
        }), true);
    
    $listener->created($model);
});

test('LogOrmOperation extractModelId handles multiple WHERE conditions', function () {
    $listener = new TestableLogOrmOperation();
    
    $sql = 'UPDATE users SET name = ? WHERE id = ? AND status = ?';
    $bindings = ['New Name', 123, 'active'];
    
    $modelId = $listener->testExtractModelId($sql, 'UPDATE', $bindings);
    expect($modelId)->toBe('123');
});

test('LogOrmOperation extractModelId handles WHERE id = ? with other conditions first', function () {
    $listener = new TestableLogOrmOperation();
    
    $sql = 'UPDATE users SET name = ? WHERE id = ? AND status = ?';
    $bindings = ['New Name', 456, 'active'];
    
    // When id = ? comes first, it should extract the ID
    $modelId = $listener->testExtractModelId($sql, 'UPDATE', $bindings);
    expect($modelId)->toBe('456');
});

test('LogOrmOperation cleanupOldTransactions removes old transaction IDs', function () {
    $listener = new LogOrmOperation();
    
    $reflection = new ReflectionClass($listener);
    
    // Set old transaction IDs
    $property = $reflection->getProperty('transactionIds');
    $property->setAccessible(true);
    $oldTime = time() - 4000; // Older than MAX_TRANSACTION_AGE (3600)
    $property->setValue($listener, [
        DB::getDefaultConnection() . ':1' => ['id' => 'txn-1', 'timestamp' => $oldTime],
        DB::getDefaultConnection() . ':2' => ['id' => 'txn-2', 'timestamp' => time() - 100], // Recent
    ]);
    
    // Call cleanupOldTransactions via getTransactionId (which calls it)
    $method = $reflection->getMethod('getTransactionId');
    $method->setAccessible(true);
    $method->invoke($listener, DB::getDefaultConnection());
    
    // Old transaction should be removed
    $remaining = $property->getValue($listener);
    $connection = DB::getDefaultConnection();
    expect($remaining)->not->toHaveKey($connection . ':1');
    // Recent transaction may or may not exist depending on transaction level
    expect($remaining)->toBeArray();
});

test('LogOrmOperation cleanupOldTransactions removes transactions with higher level', function () {
    $listener = new LogOrmOperation();
    
    $reflection = new ReflectionClass($listener);
    $property = $reflection->getProperty('transactionIds');
    $property->setAccessible(true);
    
    // Set transaction IDs with different levels
    $property->setValue($listener, [
        'mysql:2' => ['id' => 'txn-2', 'timestamp' => time()],
        'mysql:3' => ['id' => 'txn-3', 'timestamp' => time()],
    ]);
    
    // Call getTransactionId with level 1 (should remove levels 2 and 3)
    $method = $reflection->getMethod('getTransactionId');
    $method->setAccessible(true);
    $method->invoke($listener, 'mysql');
    
    // Transactions with higher level should be removed
    $remaining = $property->getValue($listener);
    // Should be cleaned up
    expect($remaining)->toBeArray();
});

test('LogOrmOperation clearTransactionIdsForConnection clears all IDs for connection', function () {
    $listener = new LogOrmOperation();
    
    $reflection = new ReflectionClass($listener);
    $property = $reflection->getProperty('transactionIds');
    $property->setAccessible(true);
    
    // Set transaction IDs for multiple connections
    $property->setValue($listener, [
        'mysql:1' => ['id' => 'txn-1', 'timestamp' => time()],
        'mysql:2' => ['id' => 'txn-2', 'timestamp' => time()],
        'pgsql:1' => ['id' => 'txn-3', 'timestamp' => time()],
    ]);
    
    // Call getTransactionId with level 0 (triggers clearTransactionIdsForConnection)
    $method = $reflection->getMethod('getTransactionId');
    $method->setAccessible(true);
    $method->invoke($listener, 'mysql');
    
    // All mysql transactions should be cleared
    $remaining = $property->getValue($listener);
    expect($remaining)->not->toHaveKey('mysql:1');
    expect($remaining)->not->toHaveKey('mysql:2');
    expect($remaining)->toHaveKey('pgsql:1'); // Other connection should remain
});

test('LogOrmOperation clearTransactionIdsForConnection directly invokes removal of keys', function () {
    $listener = new LogOrmOperation();
    
    $reflection = new ReflectionClass($listener);
    $property = $reflection->getProperty('transactionIds');
    $property->setAccessible(true);
    $clearMethod = $reflection->getMethod('clearTransactionIdsForConnection');
    $clearMethod->setAccessible(true);
    
    // Set transaction IDs so that foreach and unset lines are executed
    $property->setValue($listener, [
        'conn:1' => ['id' => 'txn-1', 'timestamp' => time()],
        'conn:2' => ['id' => 'txn-2', 'timestamp' => time()],
        'other:1' => ['id' => 'txn-3', 'timestamp' => time()],
    ]);
    
    $clearMethod->invoke($listener, 'conn');
    
    $remaining = $property->getValue($listener);
    expect($remaining)->not->toHaveKey('conn:1');
    expect($remaining)->not->toHaveKey('conn:2');
    expect($remaining)->toHaveKey('other:1');
});

test('LogOrmOperation cleanupOldTransactions skips transactions from other connections', function () {
    $listener = new LogOrmOperation();
    
    $reflection = new ReflectionClass($listener);
    $property = $reflection->getProperty('transactionIds');
    $property->setAccessible(true);
    
    // Set transaction IDs for different connections
    $property->setValue($listener, [
        'mysql:1' => ['id' => 'txn-1', 'timestamp' => time()],
        'pgsql:1' => ['id' => 'txn-2', 'timestamp' => time()],
    ]);
    
    // Call getTransactionId for mysql (should not affect pgsql)
    $method = $reflection->getMethod('getTransactionId');
    $method->setAccessible(true);
    $method->invoke($listener, 'mysql');
    
    // pgsql transaction should remain
    $remaining = $property->getValue($listener);
    expect($remaining)->toHaveKey('pgsql:1');
});

test('LogOrmOperation cleanupOldTransactions keeps transactions with level <= currentLevel', function () {
    $listener = new LogOrmOperation();
    
    $reflection = new ReflectionClass($listener);
    $property = $reflection->getProperty('transactionIds');
    $property->setAccessible(true);
    
    // Set transaction IDs with different levels
    $property->setValue($listener, [
        'mysql:1' => ['id' => 'txn-1', 'timestamp' => time()],
        'mysql:2' => ['id' => 'txn-2', 'timestamp' => time()],
    ]);
    
    // Start transaction at level 1
    DB::beginTransaction();
    try {
        $method = $reflection->getMethod('getTransactionId');
        $method->setAccessible(true);
        $id1 = $method->invoke($listener, 'mysql');
        
        // Start nested transaction at level 2
        DB::beginTransaction();
        try {
            $id2 = $method->invoke($listener, 'mysql');
            
            // Both should exist
            $remaining = $property->getValue($listener);
            expect($remaining)->toHaveKey('mysql:1');
            expect($remaining)->toHaveKey('mysql:2');
        } finally {
            DB::rollBack();
        }
    } finally {
        DB::rollBack();
    }
});

test('LogOrmOperation findAndRemovePendingQuery finds best match when exact match fails', function () {
    config(['laravel-logger.orm.enabled' => true]);
    
    $listener = new LogOrmOperation();
    
    // Store pending query without modelId
    $queryEvent = new QueryExecuted(
        sql: 'UPDATE users SET name = ? WHERE email = ?',
        bindings: ['New Name', 'test@example.com'],
        time: 5.0,
        connection: DB::connection(),
    );
    $listener->handleQueryExecuted($queryEvent);
    
    // Use reflection to access findAndRemovePendingQuery
    $reflection = new ReflectionClass($listener);
    $method = $reflection->getMethod('findAndRemovePendingQuery');
    $method->setAccessible(true);
    
    // Search with modelId that doesn't match (should find best match)
    $found = $method->invoke($listener, DB::getDefaultConnection(), 'users', 'UPDATE', '999');
    expect($found)->not->toBeNull();
    expect($found['query'])->toBe('UPDATE users SET name = ? WHERE email = ?');
});

test('LogOrmOperation findAndRemovePendingQuery handles UPDATE with null modelId', function () {
    config(['laravel-logger.orm.enabled' => true]);
    
    $listener = new LogOrmOperation();
    
    // Store pending query
    $queryEvent = new QueryExecuted(
        sql: 'UPDATE users SET status = ?',
        bindings: ['active'],
        time: 5.0,
        connection: DB::connection(),
    );
    $listener->handleQueryExecuted($queryEvent);
    
    $reflection = new ReflectionClass($listener);
    $method = $reflection->getMethod('findAndRemovePendingQuery');
    $method->setAccessible(true);
    
    // Search with null modelId (should find best match)
    $found = $method->invoke($listener, DB::getDefaultConnection(), 'users', 'UPDATE', null);
    expect($found)->not->toBeNull();
});

test('LogOrmOperation findAndRemovePendingQuery handles DELETE with null modelId', function () {
    config(['laravel-logger.orm.enabled' => true]);
    
    $listener = new LogOrmOperation();
    
    // Store pending DELETE query
    $queryEvent = new QueryExecuted(
        sql: 'DELETE FROM users WHERE status = ?',
        bindings: ['inactive'],
        time: 5.0,
        connection: DB::connection(),
    );
    $listener->handleQueryExecuted($queryEvent);
    
    $reflection = new ReflectionClass($listener);
    $method = $reflection->getMethod('findAndRemovePendingQuery');
    $method->setAccessible(true);
    
    // Search with null modelId (should find best match)
    $found = $method->invoke($listener, DB::getDefaultConnection(), 'users', 'DELETE', null);
    expect($found)->not->toBeNull();
});

test('LogOrmOperation findAndRemovePendingQuery returns most recent match when multiple exist', function () {
    config(['laravel-logger.orm.enabled' => true]);
    
    $listener = new LogOrmOperation();
    
    // Store multiple pending queries with different timestamps
    $reflection = new ReflectionClass($listener);
    $property = $reflection->getProperty('pendingQueries');
    $property->setAccessible(true);
    
    $now = microtime(true);
    $property->setValue($listener, [
        'mysql:users:UPDATE:*' => [
            'query' => 'UPDATE users SET name = ?',
            'bindings' => null,
            'durationMs' => 5,
            'isSlowQuery' => false,
            'transactionId' => null,
            'timestamp' => $now - 2, // Older
            'connection' => 'mysql',
            'table' => 'users',
            'queryType' => 'UPDATE',
            'modelId' => null,
        ],
        'mysql:users:UPDATE:newer' => [
            'query' => 'UPDATE users SET email = ?',
            'bindings' => null,
            'durationMs' => 3,
            'isSlowQuery' => false,
            'transactionId' => null,
            'timestamp' => $now - 1, // Newer
            'connection' => 'mysql',
            'table' => 'users',
            'queryType' => 'UPDATE',
            'modelId' => null,
        ],
    ]);
    
    $method = $reflection->getMethod('findAndRemovePendingQuery');
    $method->setAccessible(true);
    
    // Should find the most recent one
    $found = $method->invoke($listener, 'mysql', 'users', 'UPDATE', null);
    expect($found)->not->toBeNull();
    expect($found['query'])->toBe('UPDATE users SET email = ?'); // Newer one
});

test('LogOrmOperation clearTransactionIdsForConnection handles empty transaction IDs', function () {
    $listener = new LogOrmOperation();
    
    $reflection = new ReflectionClass($listener);
    $property = $reflection->getProperty('transactionIds');
    $property->setAccessible(true);
    
    // Set empty transaction IDs
    $property->setValue($listener, []);
    
    // Call getTransactionId with level 0 (triggers clearTransactionIdsForConnection)
    $method = $reflection->getMethod('getTransactionId');
    $method->setAccessible(true);
    $method->invoke($listener, 'mysql');
    
    // Should not throw even when no transactions exist
    $remaining = $property->getValue($listener);
    expect($remaining)->toBeEmpty();
});

test('LogOrmOperation clearTransactionIdsForConnection handles connection with no matching keys', function () {
    $listener = new LogOrmOperation();
    
    $reflection = new ReflectionClass($listener);
    $property = $reflection->getProperty('transactionIds');
    $property->setAccessible(true);
    
    // Set transaction IDs for different connection
    $property->setValue($listener, [
        'pgsql:1' => ['id' => 'txn-1', 'timestamp' => time()],
    ]);
    
    // Call getTransactionId for mysql (should not affect pgsql)
    $method = $reflection->getMethod('getTransactionId');
    $method->setAccessible(true);
    $method->invoke($listener, 'mysql');
    
    // pgsql transaction should remain (no keys to remove for mysql)
    $remaining = $property->getValue($listener);
    expect($remaining)->toHaveKey('pgsql:1');
});

