<?php

use Ermetix\LaravelLogger\Facades\LaravelLogger;
use Ermetix\LaravelLogger\Listeners\LogDatabaseQuery;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

test('LogDatabaseQuery extractQueryType handles various SQL formats', function () {
    $listener = new LogDatabaseQuery();
    
    $reflection = new ReflectionClass($listener);
    $method = $reflection->getMethod('extractQueryType');
    $method->setAccessible(true);
    
    // Standard queries
    expect($method->invoke($listener, 'SELECT * FROM users'))->toBe('SELECT');
    expect($method->invoke($listener, 'INSERT INTO users VALUES (?)'))->toBe('INSERT');
    expect($method->invoke($listener, 'UPDATE users SET name = ?'))->toBe('UPDATE');
    expect($method->invoke($listener, 'DELETE FROM users WHERE id = ?'))->toBe('DELETE');
    
    // Queries with whitespace
    expect($method->invoke($listener, '   SELECT * FROM users'))->toBe('SELECT');
    expect($method->invoke($listener, "\n\tSELECT * FROM users"))->toBe('SELECT');
    
    // Case insensitive
    expect($method->invoke($listener, 'select * from users'))->toBe('SELECT');
    expect($method->invoke($listener, 'SeLeCt * FrOm users'))->toBe('SELECT');
    
    // Other query types
    expect($method->invoke($listener, 'CREATE TABLE users'))->toBe('CREATE');
    expect($method->invoke($listener, 'DROP TABLE users'))->toBe('DROP');
    expect($method->invoke($listener, 'ALTER TABLE users'))->toBe('ALTER');
    expect($method->invoke($listener, 'TRUNCATE TABLE users'))->toBe('TRUNCATE');
    expect($method->invoke($listener, 'REPLACE INTO users'))->toBe('REPLACE');
    
    // Invalid queries
    expect($method->invoke($listener, 'INVALID QUERY'))->toBeNull();
    expect($method->invoke($listener, ''))->toBeNull();
});

test('LogDatabaseQuery extractTable handles various table name formats', function () {
    $listener = new LogDatabaseQuery();
    
    $reflection = new ReflectionClass($listener);
    $method = $reflection->getMethod('extractTable');
    $method->setAccessible(true);
    
    // Standard formats
    expect($method->invoke($listener, 'SELECT * FROM users', 'SELECT'))->toBe('users');
    expect($method->invoke($listener, 'SELECT * FROM `users`', 'SELECT'))->toBe('users');
    expect($method->invoke($listener, 'SELECT * FROM "users"', 'SELECT'))->toBe('users');
    
    // INSERT/UPDATE/DELETE
    expect($method->invoke($listener, 'INSERT INTO test_users VALUES (?)', 'INSERT'))->toBe('test_users');
    expect($method->invoke($listener, 'UPDATE `users` SET name = ?', 'UPDATE'))->toBe('users');
    expect($method->invoke($listener, 'DELETE FROM "users" WHERE id = ?', 'DELETE'))->toBe('users');
    
    // CREATE TABLE
    expect($method->invoke($listener, 'CREATE TABLE migrations', 'CREATE'))->toBe('migrations');
    
    // Complex queries
    expect($method->invoke($listener, 'SELECT u.*, p.* FROM users u JOIN profiles p', 'SELECT'))->toBe('users');
    expect($method->invoke($listener, 'UPDATE users SET name = ? WHERE id = ?', 'UPDATE'))->toBe('users');
    
    // No table found
    expect($method->invoke($listener, 'SELECT 1', 'SELECT'))->toBeNull();
});

test('LogDatabaseQuery extractModel handles various table names', function () {
    $listener = new LogDatabaseQuery();
    
    $reflection = new ReflectionClass($listener);
    $method = $reflection->getMethod('extractModel');
    $method->setAccessible(true);
    
    // Null table returns null
    expect($method->invoke($listener, 'SELECT 1', null))->toBeNull();
    
    // Table name conversion (model may or may not exist, so we just check it's a string or null)
    $result = $method->invoke($listener, 'SELECT * FROM users', 'users');
    expect($result === null || is_string($result))->toBeTrue();
    
    $result = $method->invoke($listener, 'SELECT * FROM user_profiles', 'user_profiles');
    expect($result === null || is_string($result))->toBeTrue();
    
    // Non-existent table should return null
    $result = $method->invoke($listener, 'SELECT * FROM nonexistent_table_xyz', 'nonexistent_table_xyz');
    expect($result)->toBeNull();
});

test('LogDatabaseQuery extractModelId handles various WHERE clause formats', function () {
    $listener = new LogDatabaseQuery();
    
    $reflection = new ReflectionClass($listener);
    $method = $reflection->getMethod('extractModelId');
    $method->setAccessible(true);
    
    // Standard WHERE id = ?
    expect($method->invoke($listener, 'UPDATE users SET name = ? WHERE id = ?', 'UPDATE', ['John', 123]))->toBe('123');
    expect($method->invoke($listener, 'UPDATE users SET name = ? WHERE `id` = ?', 'UPDATE', ['John', 456]))->toBe('456');
    expect($method->invoke($listener, 'UPDATE users SET name = ? WHERE "id" = ?', 'UPDATE', ['John', 789]))->toBe('789');
    
    // DELETE with id
    expect($method->invoke($listener, 'DELETE FROM users WHERE id = ?', 'DELETE', [123]))->toBe('123');
    
    // SELECT with id (for single record queries)
    expect($method->invoke($listener, 'SELECT * FROM users WHERE id = ?', 'SELECT', [123]))->toBe('123');
    
    // No id in WHERE clause
    expect($method->invoke($listener, 'UPDATE users SET name = ? WHERE email = ?', 'UPDATE', ['John', 'test@example.com']))->toBeNull();
    
    // Query type that doesn't support model ID extraction
    expect($method->invoke($listener, 'INSERT INTO users VALUES (?)', 'INSERT', [123]))->toBeNull();
    
    // Multiple WHERE conditions
    expect($method->invoke($listener, 'UPDATE users SET name = ? WHERE id = ? AND active = ?', 'UPDATE', ['John', 123, 1]))->toBe('123');
});

test('LogDatabaseQuery mapQueryTypeToAction maps all query types correctly', function () {
    $listener = new LogDatabaseQuery();
    
    $reflection = new ReflectionClass($listener);
    $method = $reflection->getMethod('mapQueryTypeToAction');
    $method->setAccessible(true);
    
    expect($method->invoke($listener, 'SELECT'))->toBe('read');
    expect($method->invoke($listener, 'INSERT'))->toBe('create');
    expect($method->invoke($listener, 'UPDATE'))->toBe('update');
    expect($method->invoke($listener, 'DELETE'))->toBe('delete');
    
    // Other query types return lowercase version
    expect($method->invoke($listener, 'CREATE'))->toBe('create');
    expect($method->invoke($listener, 'DROP'))->toBe('drop');
    expect($method->invoke($listener, null))->toBe('unknown');
});

test('LogDatabaseQuery shouldIgnoreQuery is case insensitive', function () {
    config(['laravel-logger.orm.ignore_patterns' => ['select * from `migrations`']]);
    
    $listener = new LogDatabaseQuery();
    
    $reflection = new ReflectionClass($listener);
    $method = $reflection->getMethod('shouldIgnoreQuery');
    $method->setAccessible(true);
    
    expect($method->invoke($listener, 'SELECT * FROM `migrations`'))->toBeTrue();
    expect($method->invoke($listener, 'select * from `migrations`'))->toBeTrue();
    expect($method->invoke($listener, 'SELECT * FROM `MIGRATIONS`'))->toBeTrue();
    expect($method->invoke($listener, 'SELECT * FROM users'))->toBeFalse();
});

test('LogDatabaseQuery formatBindings handles various binding types', function () {
    $listener = new LogDatabaseQuery();
    
    $reflection = new ReflectionClass($listener);
    $method = $reflection->getMethod('formatBindings');
    $method->setAccessible(true);
    
    // Standard bindings
    $bindings = ['test@example.com', 123, true, false, null];
    $formatted = $method->invoke($listener, $bindings);
    
    expect($formatted)->toBeString();
    $decoded = json_decode($formatted, true);
    expect($decoded)->toBe($bindings);
    
    // Empty bindings returns null
    expect($method->invoke($listener, []))->toBeNull();
    
    // Large bindings (should be truncated by config, but formatBindings just formats)
    $largeBindings = array_fill(0, 100, 'test');
    $formatted = $method->invoke($listener, $largeBindings);
    expect($formatted)->toBeString();
    $decoded = json_decode($formatted, true);
    expect(count($decoded))->toBe(100);
});

test('LogDatabaseQuery handles queries without table name', function () {
    config(['laravel-logger.orm.enabled' => true]);
    config(['laravel-logger.orm.log_read_operations' => true]);
    
    LaravelLogger::shouldReceive('orm')
        ->once()
        ->with(\Mockery::on(function ($logObject) {
            return $logObject instanceof \Ermetix\LaravelLogger\Support\Logging\Objects\OrmLogObject
                && $logObject->table === null;
        }), true);
    
    $listener = new LogDatabaseQuery();
    
    $event = new QueryExecuted(
        sql: 'SELECT 1', // No table
        bindings: [],
        time: 1.0,
        connection: DB::connection(),
    );
    
    $listener->handle($event);
});

test('LogDatabaseQuery handles queries with null query type', function () {
    config(['laravel-logger.orm.enabled' => true]);
    config(['laravel-logger.orm.log_read_operations' => true]);
    
    LaravelLogger::shouldReceive('orm')
        ->once()
        ->with(\Mockery::on(function ($logObject) {
            return $logObject instanceof \Ermetix\LaravelLogger\Support\Logging\Objects\OrmLogObject
                && $logObject->queryType === null
                && $logObject->action === 'unknown'; // mapQueryTypeToAction returns 'unknown' for null
        }), true);
    
    $listener = new LogDatabaseQuery();
    
    $event = new QueryExecuted(
        sql: 'INVALID SQL QUERY', // No valid query type
        bindings: [],
        time: 1.0,
        connection: DB::connection(),
    );
    
    $listener->handle($event);
});
