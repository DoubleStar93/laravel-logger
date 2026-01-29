<?php

use Ermetix\LaravelLogger\Facades\LaravelLogger;
use Ermetix\LaravelLogger\Listeners\LogOrmOperation;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    // Reset facade so previous test mocks do not leak
    LaravelLogger::clearResolvedInstance('laravel_logger');
    
    // Enable ORM logging for tests
    config(['laravel-logger.orm.enabled' => true]);
    config(['laravel-logger.orm.slow_query_threshold_ms' => 1000]);
    config(['laravel-logger.orm.ignore_patterns' => []]);
    config(['laravel-logger.orm.log_read_operations' => true]);
    
    // Create test table
    try {
        DB::statement('CREATE TABLE IF NOT EXISTS test_users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT,
            email TEXT,
            created_at DATETIME,
            updated_at DATETIME
        )');
    } catch (\Exception $e) {
        // Table might already exist
    }
});

test('LogOrmOperation combines QueryExecuted and Eloquent created event into single log', function () {
    config(['laravel-logger.orm.enabled' => true]);
    
    LaravelLogger::shouldReceive('orm')
        ->atLeast()->once()
        ->with(\Mockery::type(\Ermetix\LaravelLogger\Support\Logging\Objects\OrmLogObject::class), true);
    
    $listener = new LogOrmOperation();
    
    // Simulate QueryExecuted event first
    $queryEvent = new QueryExecuted(
        sql: 'INSERT INTO test_users (name, email) VALUES (?, ?)',
        bindings: ['Test User', 'test@example.com'],
        time: 5.5,
        connection: DB::connection(),
    );
    $listener->handleQueryExecuted($queryEvent);
    
    // Simulate Eloquent created event
    $model = new class extends Model {
        protected $table = 'test_users';
        protected $fillable = ['name', 'email'];
    };
    $model->setRawAttributes(['id' => 1, 'name' => 'Test User', 'email' => 'test@example.com']);
    
    $listener->created($model);
});

test('LogOrmOperation combines QueryExecuted and Eloquent updated event into single log', function () {
    config(['laravel-logger.orm.enabled' => true]);
    
    LaravelLogger::shouldReceive('orm')
        ->atLeast()->once()
        ->with(\Mockery::type(\Ermetix\LaravelLogger\Support\Logging\Objects\OrmLogObject::class), true);
    
    $listener = new LogOrmOperation();
    
    // Simulate QueryExecuted event first
    $queryEvent = new QueryExecuted(
        sql: 'UPDATE test_users SET name = ? WHERE id = ?',
        bindings: ['Updated Name', 1],
        time: 3.2,
        connection: DB::connection(),
    );
    $listener->handleQueryExecuted($queryEvent);
    
    // Simulate Eloquent updated event
    $model = new class extends Model {
        protected $table = 'test_users';
        protected $fillable = ['name', 'email'];
    };
    $model->setRawAttributes(['id' => 1, 'name' => 'Old Name', 'email' => 'old@example.com']);
    $model->syncOriginal();
    $model->setAttribute('name', 'Updated Name');
    
    $listener->updated($model);
});

test('LogOrmOperation combines QueryExecuted and Eloquent deleted event into single log', function () {
    config(['laravel-logger.orm.enabled' => true]);
    
    LaravelLogger::shouldReceive('orm')
        ->atLeast()->once()
        ->with(\Mockery::type(\Ermetix\LaravelLogger\Support\Logging\Objects\OrmLogObject::class), true);
    
    $listener = new LogOrmOperation();
    
    // Simulate QueryExecuted event first
    $queryEvent = new QueryExecuted(
        sql: 'DELETE FROM test_users WHERE id = ?',
        bindings: [1],
        time: 2.1,
        connection: DB::connection(),
    );
    $listener->handleQueryExecuted($queryEvent);
    
    // Simulate Eloquent deleted event
    $model = new class extends Model {
        protected $table = 'test_users';
        protected $fillable = ['name', 'email'];
    };
    $model->setRawAttributes(['id' => 1, 'name' => 'Test User', 'email' => 'test@example.com']);
    $model->syncOriginal();
    
    $listener->deleted($model);
});

test('LogOrmOperation logs SELECT queries immediately without waiting for Eloquent event', function () {
    config(['laravel-logger.orm.enabled' => true]);
    config(['laravel-logger.orm.log_read_operations' => true]);
    
    LaravelLogger::shouldReceive('orm')
        ->once()
        ->with(\Mockery::on(function ($logObject) {
            return $logObject instanceof \Ermetix\LaravelLogger\Support\Logging\Objects\OrmLogObject
                && $logObject->message() === 'database_query'
                && $logObject->queryType === 'SELECT'
                && $logObject->query !== null
                && $logObject->previousValue === null
                && $logObject->afterValue === null;
        }), true);
    
    $listener = new LogOrmOperation();
    
    $queryEvent = new QueryExecuted(
        sql: 'SELECT * FROM test_users WHERE id = ?',
        bindings: [1],
        time: 1.5,
        connection: DB::connection(),
    );
    
    $listener->handleQueryExecuted($queryEvent);
});

test('LogOrmOperation logs model event only when no matching query found', function () {
    config(['laravel-logger.orm.enabled' => true]);
    
    LaravelLogger::shouldReceive('orm')
        ->atLeast()->once()
        ->with(\Mockery::type(\Ermetix\LaravelLogger\Support\Logging\Objects\OrmLogObject::class), true);
    
    $listener = new LogOrmOperation();
    
    // Simulate Eloquent created event without prior QueryExecuted
    $model = new class extends Model {
        protected $table = 'test_users';
        protected $fillable = ['name', 'email'];
    };
    $model->setRawAttributes(['id' => 1, 'name' => 'Test User', 'email' => 'test@example.com']);
    
    $listener->created($model);
});

test('LogOrmOperation does not log when disabled', function () {
    config(['laravel-logger.orm.enabled' => false]);
    
    LaravelLogger::shouldReceive('orm')->never();
    
    $listener = new LogOrmOperation();
    
    $queryEvent = new QueryExecuted(
        sql: 'SELECT * FROM test_users',
        bindings: [],
        time: 1.0,
        connection: DB::connection(),
    );
    
    $listener->handleQueryExecuted($queryEvent);
    
    $model = new class extends Model {
        protected $table = 'test_users';
    };
    $listener->created($model);
});

test('LogOrmOperation skips SELECT queries when log_read_operations is false', function () {
    config(['laravel-logger.orm.enabled' => true]);
    config(['laravel-logger.orm.log_read_operations' => false]);
    
    LaravelLogger::shouldReceive('orm')->never();
    
    $listener = new LogOrmOperation();
    
    $queryEvent = new QueryExecuted(
        sql: 'SELECT * FROM test_users',
        bindings: [],
        time: 1.0,
        connection: DB::connection(),
    );
    
    $listener->handleQueryExecuted($queryEvent);
});

test('LogOrmOperation ignores queries matching ignore patterns', function () {
    config(['laravel-logger.orm.enabled' => true]);
    config(['laravel-logger.orm.ignore_patterns' => ['select * from `migrations`']]);
    
    LaravelLogger::shouldReceive('orm')->never();
    
    $listener = new LogOrmOperation();
    
    $queryEvent = new QueryExecuted(
        sql: 'SELECT * FROM `migrations`',
        bindings: [],
        time: 1.0,
        connection: DB::connection(),
    );
    
    $listener->handleQueryExecuted($queryEvent);
});

test('LogOrmOperation detects slow queries', function () {
    config(['laravel-logger.orm.enabled' => true]);
    config(['laravel-logger.orm.slow_query_threshold_ms' => 10]);
    config(['laravel-logger.orm.log_read_operations' => true]);
    
    LaravelLogger::shouldReceive('orm')
        ->once()
        ->with(\Mockery::on(function ($logObject) {
            return $logObject instanceof \Ermetix\LaravelLogger\Support\Logging\Objects\OrmLogObject
                && $logObject->isSlowQuery === true
                && $logObject->level() === 'warning';
        }), true);
    
    $listener = new LogOrmOperation();
    
    $queryEvent = new QueryExecuted(
        sql: 'SELECT * FROM test_users',
        bindings: [],
        time: 15.0, // 15ms, above threshold
        connection: DB::connection(),
    );
    
    $listener->handleQueryExecuted($queryEvent);
});

test('LogOrmOperation extracts query type correctly', function () {
    $listener = new LogOrmOperation();
    
    $reflection = new ReflectionClass($listener);
    $method = $reflection->getMethod('extractQueryType');
    $method->setAccessible(true);
    
    expect($method->invoke($listener, 'SELECT * FROM users'))->toBe('SELECT');
    expect($method->invoke($listener, 'INSERT INTO users VALUES (?)'))->toBe('INSERT');
    expect($method->invoke($listener, 'UPDATE users SET name = ?'))->toBe('UPDATE');
    expect($method->invoke($listener, 'DELETE FROM users WHERE id = ?'))->toBe('DELETE');
});

test('LogOrmOperation extracts table name correctly', function () {
    $listener = new LogOrmOperation();
    
    $reflection = new ReflectionClass($listener);
    $method = $reflection->getMethod('extractTable');
    $method->setAccessible(true);
    
    expect($method->invoke($listener, 'SELECT * FROM users', 'SELECT'))->toBe('users');
    expect($method->invoke($listener, 'INSERT INTO test_users VALUES (?)', 'INSERT'))->toBe('test_users');
    expect($method->invoke($listener, 'UPDATE `users` SET name = ?', 'UPDATE'))->toBe('users');
    expect($method->invoke($listener, 'DELETE FROM "users" WHERE id = ?', 'DELETE'))->toBe('users');
});

test('LogOrmOperation formats bindings correctly', function () {
    $listener = new LogOrmOperation();
    
    $reflection = new ReflectionClass($listener);
    $method = $reflection->getMethod('formatBindings');
    $method->setAccessible(true);
    
    $bindings = ['test@example.com', 123];
    $formatted = $method->invoke($listener, $bindings);
    
    expect($formatted)->not->toBeNull();
    expect($formatted)->toBeString();
    expect(json_decode($formatted, true))->toBe($bindings);
});

test('LogOrmOperation maps query type to action correctly', function () {
    $listener = new LogOrmOperation();
    
    $reflection = new ReflectionClass($listener);
    $method = $reflection->getMethod('mapQueryTypeToAction');
    $method->setAccessible(true);
    
    expect($method->invoke($listener, 'SELECT'))->toBe('read');
    expect($method->invoke($listener, 'INSERT'))->toBe('create');
    expect($method->invoke($listener, 'UPDATE'))->toBe('update');
    expect($method->invoke($listener, 'DELETE'))->toBe('delete');
});

test('LogOrmOperation generates transaction ID for queries in transaction', function () {
    $listener = new LogOrmOperation();
    
    $reflection = new ReflectionClass($listener);
    $method = $reflection->getMethod('getTransactionId');
    $method->setAccessible(true);
    
    // Start a transaction
    DB::beginTransaction();
    
    try {
        $transactionId = $method->invoke($listener, 'sqlite');
        expect($transactionId)->not->toBeNull();
        expect($transactionId)->toStartWith('txn-');
        
        // Same transaction should return same ID
        $transactionId2 = $method->invoke($listener, 'sqlite');
        expect($transactionId2)->toBe($transactionId);
    } finally {
        DB::rollBack();
    }
    
    // After rollback, should return null
    $transactionIdAfter = $method->invoke($listener, 'sqlite');
    expect($transactionIdAfter)->toBeNull();
});
