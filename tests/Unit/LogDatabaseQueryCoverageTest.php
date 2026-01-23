<?php

use Ermetix\LaravelLogger\Listeners\LogDatabaseQuery;
use Ermetix\LaravelLogger\Facades\LaravelLogger;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

class TestLogDatabaseQuery extends LogDatabaseQuery
{
    public function shouldIgnoreQueryPublic(string $sql): bool { return $this->shouldIgnoreQuery($sql); }
    public function extractQueryTypePublic(string $sql): ?string { return $this->extractQueryType($sql); }
    public function extractTablePublic(string $sql, ?string $type): ?string { return $this->extractTable($sql, $type); }
    public function extractModelPublic(string $sql, ?string $table): ?string { return $this->extractModel($sql, $table); }
    public function extractModelIdPublic(string $sql, ?string $type, array $bindings): ?string { return $this->extractModelId($sql, $type, $bindings); }
    public function mapQueryTypeToActionPublic(?string $type): ?string { return $this->mapQueryTypeToAction($type); }
    public function formatBindingsPublic(array $bindings): ?string { return $this->formatBindings($bindings); }
    public function getTransactionIdPublic(?string $connectionName): ?string { return $this->getTransactionId($connectionName); }
    public function clearTransactionIdsForConnectionPublic(string $connectionName): void
    {
        $reflection = new ReflectionClass(LogDatabaseQuery::class);
        $method = $reflection->getMethod('clearTransactionIdsForConnection');
        $method->setAccessible(true);
        $method->invoke($this, $connectionName);
    }
    
    // Expose protected static property for testing
    public static function getTransactionIds(): array
    {
        $reflection = new ReflectionClass(LogDatabaseQuery::class);
        $property = $reflection->getProperty('transactionIds');
        $property->setAccessible(true);
        return $property->getValue();
    }
    
    public static function setTransactionIds(array $ids): void
    {
        $reflection = new ReflectionClass(LogDatabaseQuery::class);
        $property = $reflection->getProperty('transactionIds');
        $property->setAccessible(true);
        $property->setValue(null, $ids);
    }
}

test('LogDatabaseQuery handle returns early when orm logging is disabled', function () {
    config(['laravel-logger.orm.enabled' => false]);

    LaravelLogger::shouldReceive('orm')->never();

    $connection = \Mockery::mock(\Illuminate\Database\Connection::class);
    $connection->shouldReceive('getName')->andReturn('sqlite');

    (new LogDatabaseQuery())->handle(new QueryExecuted('select 1', [], 1.0, $connection));
});

test('LogDatabaseQuery handle returns early when query matches ignore patterns', function () {
    config([
        'laravel-logger.orm.enabled' => true,
        'laravel-logger.orm.ignore_patterns' => ['select 1'],
    ]);

    LaravelLogger::shouldReceive('orm')->never();

    $connection = \Mockery::mock(\Illuminate\Database\Connection::class);
    $connection->shouldReceive('getName')->andReturn('sqlite');

    (new LogDatabaseQuery())->handle(new QueryExecuted('select 1', [], 1.0, $connection));
});

test('LogDatabaseQuery helpers cover edge cases', function () {
    $l = new TestLogDatabaseQuery();

    expect($l->extractQueryTypePublic('   SELECT * FROM users'))->toBe('SELECT');
    expect($l->extractQueryTypePublic('nonsense'))->toBeNull();

    expect($l->extractTablePublic('SELECT * FROM users', 'SELECT'))->toBe('users');
    expect($l->extractTablePublic('nonsense', null))->toBeNull();
    expect($l->extractModelPublic('select 1', null))->toBeNull();

    expect($l->mapQueryTypeToActionPublic('SELECT'))->toBe('read');
    expect($l->mapQueryTypeToActionPublic(null))->toBe('unknown');

    expect($l->formatBindingsPublic([]))->toBeNull();
    $big = array_fill(0, 3000, 'x');
    expect($l->formatBindingsPublic($big))->toContain('[truncated]');
});

test('LogDatabaseQuery extractModel detects App\\Models model by convention', function () {
    if (!class_exists('App\\Models\\User')) {
        eval('namespace App\\Models; class User extends \\Illuminate\\Database\\Eloquent\\Model {}');
    }

    $l = new TestLogDatabaseQuery();
    expect($l->extractModelPublic('select * from users', 'users'))->toBe('App\\Models\\User');
});

test('LogDatabaseQuery getTransactionId returns null on DB errors', function () {
    $l = new TestLogDatabaseQuery();

    DB::shouldReceive('getDefaultConnection')->andReturn('sqlite');
    DB::shouldReceive('connection')->andThrow(new RuntimeException('boom'));

    expect($l->getTransactionIdPublic('sqlite'))->toBeNull();
});

test('LogDatabaseQuery getTransactionId falls back to default transactionLevel', function () {
    $l = new TestLogDatabaseQuery();

    DB::shouldReceive('getDefaultConnection')->andReturn('sqlite');

    $conn = \Mockery::mock();
    $conn->shouldReceive('transactionLevel')->andReturn(0);
    DB::shouldReceive('connection')->with('sqlite')->andReturn($conn);

    DB::shouldReceive('transactionLevel')->andReturn(1);

    $id = $l->getTransactionIdPublic('sqlite');
    expect($id)->toStartWith('txn-');
});

test('LogDatabaseQuery cleanupOldTransactions skips keys from other connections', function () {
    $l = new TestLogDatabaseQuery();
    
    // Set up transaction IDs for different connections
    TestLogDatabaseQuery::setTransactionIds([
        'mysql:1' => ['id' => 'txn-1', 'timestamp' => time()], // Different connection
        'sqlite:1' => ['id' => 'txn-2', 'timestamp' => time()], // Same connection
    ]);
    
    DB::shouldReceive('getDefaultConnection')->andReturn('sqlite');
    $conn = \Mockery::mock();
    $conn->shouldReceive('transactionLevel')->andReturn(1);
    DB::shouldReceive('connection')->with('sqlite')->andReturn($conn);
    
    // This should trigger cleanup, but only check sqlite keys
    $l->getTransactionIdPublic('sqlite');
    
    $ids = TestLogDatabaseQuery::getTransactionIds();
    expect($ids)->toHaveKey('mysql:1'); // Other connection kept
    expect($ids)->toHaveKey('sqlite:1'); // Same connection kept
    
    // Cleanup
    TestLogDatabaseQuery::setTransactionIds([]);
});

test('LogDatabaseQuery cleanupOldTransactions removes old transactions', function () {
    $l = new TestLogDatabaseQuery();
    
    // Set up old transaction ID (older than MAX_TRANSACTION_AGE = 3600 seconds)
    TestLogDatabaseQuery::setTransactionIds([
        'sqlite:1' => ['id' => 'txn-old', 'timestamp' => time() - 4000], // 4000 seconds ago
        'sqlite:2' => ['id' => 'txn-recent', 'timestamp' => time()], // Recent
    ]);
    
    DB::shouldReceive('getDefaultConnection')->andReturn('sqlite');
    $conn = \Mockery::mock();
    $conn->shouldReceive('transactionLevel')->andReturn(2);
    DB::shouldReceive('connection')->with('sqlite')->andReturn($conn);
    
    // This should trigger cleanup and remove old transaction
    $l->getTransactionIdPublic('sqlite');
    
    $ids = TestLogDatabaseQuery::getTransactionIds();
    expect($ids)->not->toHaveKey('sqlite:1'); // Old one removed
    expect($ids)->toHaveKey('sqlite:2'); // Recent one kept
    
    // Cleanup
    TestLogDatabaseQuery::setTransactionIds([]);
});

test('LogDatabaseQuery cleanupOldTransactions removes higher level transactions', function () {
    $l = new TestLogDatabaseQuery();
    
    // Set up transactions at different levels
    TestLogDatabaseQuery::setTransactionIds([
        'sqlite:1' => ['id' => 'txn-1', 'timestamp' => time()],
        'sqlite:2' => ['id' => 'txn-2', 'timestamp' => time()],
        'sqlite:3' => ['id' => 'txn-3', 'timestamp' => time()],
    ]);
    
    DB::shouldReceive('getDefaultConnection')->andReturn('sqlite');
    $conn = \Mockery::mock();
    $conn->shouldReceive('transactionLevel')->andReturn(1); // Current level is 1
    DB::shouldReceive('connection')->with('sqlite')->andReturn($conn);
    
    // This should trigger cleanup and remove levels 2 and 3
    $l->getTransactionIdPublic('sqlite');
    
    $ids = TestLogDatabaseQuery::getTransactionIds();
    expect($ids)->toHaveKey('sqlite:1'); // Current level kept
    expect($ids)->not->toHaveKey('sqlite:2'); // Higher level removed
    expect($ids)->not->toHaveKey('sqlite:3'); // Higher level removed
    
    // Cleanup
    TestLogDatabaseQuery::setTransactionIds([]);
});

test('LogDatabaseQuery clearTransactionIdsForConnection clears all connection IDs when level is 0', function () {
    $l = new TestLogDatabaseQuery();
    
    // Set up transactions for multiple connections
    TestLogDatabaseQuery::setTransactionIds([
        'sqlite:1' => ['id' => 'txn-1', 'timestamp' => time()],
        'sqlite:2' => ['id' => 'txn-2', 'timestamp' => time()],
        'mysql:1' => ['id' => 'txn-3', 'timestamp' => time()],
    ]);
    
    DB::shouldReceive('getDefaultConnection')->andReturn('sqlite');
    $conn = \Mockery::mock();
    $conn->shouldReceive('transactionLevel')->andReturn(0); // Not in transaction
    DB::shouldReceive('connection')->with('sqlite')->andReturn($conn);
    DB::shouldReceive('transactionLevel')->andReturn(0); // Also 0 on default
    
    // This should trigger clearTransactionIdsForConnection
    $result = $l->getTransactionIdPublic('sqlite');
    
    expect($result)->toBeNull(); // No transaction ID when level is 0
    
    $ids = TestLogDatabaseQuery::getTransactionIds();
    expect($ids)->not->toHaveKey('sqlite:1'); // Cleared
    expect($ids)->not->toHaveKey('sqlite:2'); // Cleared
    expect($ids)->toHaveKey('mysql:1'); // Other connection kept
    
    // Cleanup
    TestLogDatabaseQuery::setTransactionIds([]);
});

test('LogDatabaseQuery clearTransactionIdsForConnection directly clears all connection IDs', function () {
    $l = new TestLogDatabaseQuery();
    
    // Set up transactions for multiple connections with multiple levels
    TestLogDatabaseQuery::setTransactionIds([
        'sqlite:1' => ['id' => 'txn-1', 'timestamp' => time()],
        'sqlite:2' => ['id' => 'txn-2', 'timestamp' => time()],
        'sqlite:3' => ['id' => 'txn-3', 'timestamp' => time()],
        'mysql:1' => ['id' => 'txn-4', 'timestamp' => time()],
        'mysql:2' => ['id' => 'txn-5', 'timestamp' => time()],
    ]);
    
    // Directly call clearTransactionIdsForConnection to ensure all lines are covered
    $l->clearTransactionIdsForConnectionPublic('sqlite');
    
    $ids = TestLogDatabaseQuery::getTransactionIds();
    // All sqlite keys should be removed
    expect($ids)->not->toHaveKey('sqlite:1');
    expect($ids)->not->toHaveKey('sqlite:2');
    expect($ids)->not->toHaveKey('sqlite:3');
    // MySQL keys should remain
    expect($ids)->toHaveKey('mysql:1');
    expect($ids)->toHaveKey('mysql:2');
    
    // Cleanup
    TestLogDatabaseQuery::setTransactionIds([]);
});

test('LogDatabaseQuery clearTransactionIdsForConnection handles empty transaction IDs', function () {
    $l = new TestLogDatabaseQuery();
    
    // Set up empty transaction IDs
    TestLogDatabaseQuery::setTransactionIds([]);
    
    // Should not throw and should handle gracefully
    $l->clearTransactionIdsForConnectionPublic('sqlite');
    
    $ids = TestLogDatabaseQuery::getTransactionIds();
    expect($ids)->toBeEmpty();
    
    // Cleanup
    TestLogDatabaseQuery::setTransactionIds([]);
});

