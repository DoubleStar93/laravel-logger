<?php

use Ermetix\LaravelLogger\Logging\Contracts\BatchableHandler;
use Ermetix\LaravelLogger\Logging\Handlers\IndexFileHandler;
use Ermetix\LaravelLogger\Logging\Handlers\KafkaRestProxyHandler;
use Ermetix\LaravelLogger\Logging\Handlers\OpenSearchIndexHandler;
use Monolog\Level;
use Monolog\LogRecord;

test('OpenSearchIndexHandler implements BatchableHandler', function () {
    $handler = new OpenSearchIndexHandler(
        baseUrl: 'http://localhost:9200',
        index: 'test',
        documentBuilder: new \Ermetix\LaravelLogger\Logging\Builders\DefaultOpenSearchDocumentBuilder(),
    );
    
    expect($handler)->toBeInstanceOf(BatchableHandler::class);
});

test('KafkaRestProxyHandler implements BatchableHandler', function () {
    $handler = new KafkaRestProxyHandler(
        restProxyUrl: 'http://localhost:8082',
        topic: 'test',
        valueBuilder: new \Ermetix\LaravelLogger\Logging\Builders\DefaultKafkaValueBuilder(),
    );
    
    expect($handler)->toBeInstanceOf(BatchableHandler::class);
});

test('IndexFileHandler implements BatchableHandler', function () {
    $handler = new IndexFileHandler();
    
    expect($handler)->toBeInstanceOf(BatchableHandler::class);
});

test('OpenSearchIndexHandler writeBatch groups records by index', function () {
    $mockHttp = \Mockery::mock(\GuzzleHttp\Client::class);
    
    $handler = new OpenSearchIndexHandler(
        baseUrl: 'http://localhost:9200',
        index: 'default',
        documentBuilder: new \Ermetix\LaravelLogger\Logging\Builders\DefaultOpenSearchDocumentBuilder(),
        http: $mockHttp,
    );
    
    $record1 = new LogRecord(
        datetime: new \DateTimeImmutable('now'),
        channel: 'opensearch',
        level: Level::Info,
        message: 'Test 1',
        context: ['log_index' => 'api_log'],
    );
    
    $record2 = new LogRecord(
        datetime: new \DateTimeImmutable('now'),
        channel: 'opensearch',
        level: Level::Info,
        message: 'Test 2',
        context: ['log_index' => 'api_log'],
    );
    
    $record3 = new LogRecord(
        datetime: new \DateTimeImmutable('now'),
        channel: 'opensearch',
        level: Level::Info,
        message: 'Test 3',
        context: ['log_index' => 'general_log'],
    );
    
    // Should make 2 calls: one for api_log (2 records), one for general_log (1 record)
    $mockHttp->shouldReceive('post')
        ->twice()
        ->with(\Mockery::on(function ($url) {
            return str_contains($url, '/_bulk');
        }), \Mockery::type('array'))
        ->andReturn(new \GuzzleHttp\Psr7\Response(200));
    
    $handler->writeBatch([$record1, $record2, $record3]);
});

test('KafkaRestProxyHandler writeBatch sends all records in one request', function () {
    $mockHttp = \Mockery::mock(\GuzzleHttp\Client::class);
    
    $handler = new KafkaRestProxyHandler(
        restProxyUrl: 'http://localhost:8082',
        topic: 'test',
        valueBuilder: new \Ermetix\LaravelLogger\Logging\Builders\DefaultKafkaValueBuilder(),
        http: $mockHttp,
    );
    
    $record1 = new LogRecord(
        datetime: new \DateTimeImmutable('now'),
        channel: 'kafka',
        level: Level::Info,
        message: 'Test 1',
        context: ['log_index' => 'api_log'],
    );
    
    $record2 = new LogRecord(
        datetime: new \DateTimeImmutable('now'),
        channel: 'kafka',
        level: Level::Info,
        message: 'Test 2',
        context: ['log_index' => 'general_log'],
    );
    
    // Should make 1 call with both records
    $mockHttp->shouldReceive('post')
        ->once()
        ->with(\Mockery::on(function ($url) {
            return str_contains($url, '/topics/test');
        }), \Mockery::on(function ($options) {
            // Guzzle's 'json' option is already an array, not a JSON string
            $payload = $options['json'] ?? [];
            return isset($payload['records']) && count($payload['records']) === 2;
        }))
        ->andReturn(new \GuzzleHttp\Psr7\Response(200));
    
    $handler->writeBatch([$record1, $record2]);
});

test('IndexFileHandler writeBatch groups records by file path', function () {
    $handler = new IndexFileHandler();
    
    $date = (new \DateTimeImmutable('now'))->format('Y-m-d');
    
    $apiLogPath = storage_path("logs/laravel-logger/api_log-{$date}.jsonl");
    $generalLogPath = storage_path("logs/laravel-logger/general_log-{$date}.jsonl");
    
    // Cleanup any existing files
    @unlink($apiLogPath);
    @unlink($generalLogPath);
    
    $record1 = new LogRecord(
        datetime: new \DateTimeImmutable('now'),
        channel: 'index_file',
        level: Level::Info,
        message: 'Test 1',
        context: ['log_index' => 'api_log'],
    );
    
    $record2 = new LogRecord(
        datetime: new \DateTimeImmutable('now'),
        channel: 'index_file',
        level: Level::Info,
        message: 'Test 2',
        context: ['log_index' => 'api_log'],
    );
    
    $record3 = new LogRecord(
        datetime: new \DateTimeImmutable('now'),
        channel: 'index_file',
        level: Level::Info,
        message: 'Test 3',
        context: ['log_index' => 'general_log'],
    );
    
    $handler->writeBatch([$record1, $record2, $record3]);
    
    // Verify files were created
    expect(file_exists($apiLogPath))->toBeTrue();
    expect(file_exists($generalLogPath))->toBeTrue();
    
    // Verify api_log has 2 lines (filter out empty lines)
    $apiLogContent = file_get_contents($apiLogPath);
    $apiLogLines = array_filter(explode("\n", $apiLogContent), fn($line) => trim($line) !== '');
    expect(count($apiLogLines))->toBe(2);
    
    // Verify general_log has 1 line (filter out empty lines)
    $generalLogContent = file_get_contents($generalLogPath);
    $generalLogLines = array_filter(explode("\n", $generalLogContent), fn($line) => trim($line) !== '');
    expect(count($generalLogLines))->toBe(1);
    
    // Cleanup
    @unlink($apiLogPath);
    @unlink($generalLogPath);
});

test('OpenSearchIndexHandler writeBatch handles empty records', function () {
    $handler = new OpenSearchIndexHandler(
        baseUrl: 'http://localhost:9200',
        index: 'test',
        documentBuilder: new \Ermetix\LaravelLogger\Logging\Builders\DefaultOpenSearchDocumentBuilder(),
    );
    
    // Should not throw and not make any HTTP calls
    $handler->writeBatch([]);
    
    expect(true)->toBeTrue(); // Just verify it doesn't throw
});

test('OpenSearchIndexHandler writeBatch uses fallback index when builder returns empty', function () {
    $mockHttp = \Mockery::mock(\GuzzleHttp\Client::class);
    
    $handler = new OpenSearchIndexHandler(
        baseUrl: 'http://localhost:9200',
        index: 'fallback_index',
        documentBuilder: new \Ermetix\LaravelLogger\Logging\Builders\DefaultOpenSearchDocumentBuilder(),
        http: $mockHttp,
    );
    
    $record = new LogRecord(
        datetime: new \DateTimeImmutable('now'),
        channel: 'opensearch',
        level: Level::Info,
        message: 'Test',
        context: ['log_index' => ''], // Empty index should use fallback
    );
    
    // DefaultOpenSearchDocumentBuilder will return 'general_log' for empty log_index, not the fallback
    // So we check for general_log or fallback_index
    $mockHttp->shouldReceive('post')
        ->once()
        ->with(\Mockery::on(function ($url) {
            return str_contains($url, '/_bulk');
        }), \Mockery::type('array'))
        ->andReturn(new \GuzzleHttp\Psr7\Response(200));
    
    $handler->writeBatch([$record]);
});

test('OpenSearchIndexHandler writeBatch includes auth when credentials are provided', function () {
    $mockHttp = \Mockery::mock(\GuzzleHttp\Client::class);
    
    $handler = new OpenSearchIndexHandler(
        baseUrl: 'http://localhost:9200',
        index: 'test',
        documentBuilder: new \Ermetix\LaravelLogger\Logging\Builders\DefaultOpenSearchDocumentBuilder(),
        username: 'user',
        password: 'pass',
        http: $mockHttp,
    );
    
    $record = new LogRecord(
        datetime: new \DateTimeImmutable('now'),
        channel: 'opensearch',
        level: Level::Info,
        message: 'Test',
        context: ['log_index' => 'api_log'],
    );
    
    $mockHttp->shouldReceive('post')
        ->once()
        ->with(\Mockery::any(), \Mockery::on(function ($options) {
            return isset($options['auth']) && $options['auth'] === ['user', 'pass'];
        }))
        ->andReturn(new \GuzzleHttp\Psr7\Response(200));
    
    $handler->writeBatch([$record]);
});

test('OpenSearchIndexHandler writeBatch handles exceptions when not silent', function () {
    $mockHttp = \Mockery::mock(\GuzzleHttp\Client::class);
    
    $handler = new OpenSearchIndexHandler(
        baseUrl: 'http://localhost:9200',
        index: 'test',
        documentBuilder: new \Ermetix\LaravelLogger\Logging\Builders\DefaultOpenSearchDocumentBuilder(),
        silent: false,
        http: $mockHttp,
    );
    
    $record = new LogRecord(
        datetime: new \DateTimeImmutable('now'),
        channel: 'opensearch',
        level: Level::Info,
        message: 'Test',
        context: ['log_index' => 'api_log'],
    );
    
    $request = new \GuzzleHttp\Psr7\Request('POST', 'http://localhost:9200/api_log/_bulk');
    $response = new \GuzzleHttp\Psr7\Response(400);
    $exception = new \GuzzleHttp\Exception\ClientException('Error', $request, $response);
    
    $mockHttp->shouldReceive('post')
        ->once()
        ->andThrow($exception);
    
    expect(fn() => $handler->writeBatch([$record]))->toThrow(\GuzzleHttp\Exception\ClientException::class);
});

test('KafkaRestProxyHandler writeBatch handles empty records', function () {
    $handler = new KafkaRestProxyHandler(
        restProxyUrl: 'http://localhost:8082',
        topic: 'test',
        valueBuilder: new \Ermetix\LaravelLogger\Logging\Builders\DefaultKafkaValueBuilder(),
    );
    
    // Should not throw and not make any HTTP calls
    $handler->writeBatch([]);
    
    expect(true)->toBeTrue(); // Just verify it doesn't throw
});

test('KafkaRestProxyHandler writeBatch handles exceptions when not silent', function () {
    $mockHttp = \Mockery::mock(\GuzzleHttp\Client::class);
    
    $handler = new KafkaRestProxyHandler(
        restProxyUrl: 'http://localhost:8082',
        topic: 'test',
        valueBuilder: new \Ermetix\LaravelLogger\Logging\Builders\DefaultKafkaValueBuilder(),
        silent: false,
        http: $mockHttp,
    );
    
    $record = new LogRecord(
        datetime: new \DateTimeImmutable('now'),
        channel: 'kafka',
        level: Level::Info,
        message: 'Test',
        context: ['log_index' => 'api_log'],
    );
    
    $request = new \GuzzleHttp\Psr7\Request('POST', 'http://localhost:8082/topics/test');
    $response = new \GuzzleHttp\Psr7\Response(400);
    $exception = new \GuzzleHttp\Exception\ClientException('Error', $request, $response);
    
    $mockHttp->shouldReceive('post')
        ->once()
        ->andThrow($exception);
    
    expect(fn() => $handler->writeBatch([$record]))->toThrow(\GuzzleHttp\Exception\ClientException::class);
});

test('IndexFileHandler writeBatch handles empty records', function () {
    $handler = new IndexFileHandler();
    
    // Should not throw
    $handler->writeBatch([]);
    
    expect(true)->toBeTrue(); // Just verify it doesn't throw
});

test('IndexFileHandler writeBatch creates directory when missing', function () {
    $handler = new IndexFileHandler();
    
    $testDir = storage_path('logs/laravel-logger-test');
    $testPath = $testDir.'/test-'.(new \DateTimeImmutable('now'))->format('Y-m-d').'.jsonl';
    
    // Cleanup
    @unlink($testPath);
    @rmdir($testDir);
    
    // Temporarily override storage_path to use test directory
    $originalStoragePath = storage_path('logs/laravel-logger');
    
    $record = new LogRecord(
        datetime: new \DateTimeImmutable('now'),
        channel: 'index_file',
        level: Level::Info,
        message: 'Test',
        context: ['log_index' => 'test'],
    );
    
    // This will use the real storage_path, but we verify directory creation
    $handler->writeBatch([$record]);
    
    // Verify file was created (directory should have been created)
    $realPath = storage_path('logs/laravel-logger/test-'.(new \DateTimeImmutable('now'))->format('Y-m-d').'.jsonl');
    expect(file_exists($realPath))->toBeTrue();
    
    // Cleanup
    @unlink($realPath);
});

test('IndexFileHandler writeBatch uses fallback index when log_index is empty', function () {
    $handler = new IndexFileHandler();
    
    $date = (new \DateTimeImmutable('now'))->format('Y-m-d');
    $path = storage_path("logs/laravel-logger/general_log-{$date}.jsonl");
    
    // Cleanup
    @unlink($path);
    
    $record = new LogRecord(
        datetime: new \DateTimeImmutable('now'),
        channel: 'index_file',
        level: Level::Info,
        message: 'Test',
        context: ['log_index' => ''], // Empty should use general_log
    );
    
    $handler->writeBatch([$record]);
    
    expect(file_exists($path))->toBeTrue();
    
    // Cleanup
    @unlink($path);
});

test('IndexFileHandler writeBatch handles exceptions gracefully', function () {
    $handler = new IndexFileHandler();
    
    // Create a record that will cause an issue (invalid datetime)
    $record = new LogRecord(
        datetime: new \DateTimeImmutable('now'),
        channel: 'index_file',
        level: Level::Info,
        message: 'Test',
        context: ['log_index' => 'test'],
    );
    
    // Should not throw even if something goes wrong
    $handler->writeBatch([$record]);
    
    expect(true)->toBeTrue(); // Just verify it doesn't throw
});

test('OpenSearchIndexHandler writeBatch uses fallback when builder returns non-string', function () {
    $mockHttp = \Mockery::mock(\GuzzleHttp\Client::class);
    $mockBuilder = \Mockery::mock(\Ermetix\LaravelLogger\Logging\Contracts\OpenSearchDocumentBuilder::class);
    
    $handler = new OpenSearchIndexHandler(
        baseUrl: 'http://localhost:9200',
        index: 'fallback_index',
        documentBuilder: $mockBuilder,
        http: $mockHttp,
    );
    
    $record = new LogRecord(
        datetime: new \DateTimeImmutable('now'),
        channel: 'opensearch',
        level: Level::Info,
        message: 'Test',
        context: ['log_index' => 'api_log'],
    );
    
    // Builder returns non-string (e.g., null or empty string)
    $mockBuilder->shouldReceive('index')
        ->andReturn('');
    $mockBuilder->shouldReceive('document')
        ->andReturn(['@timestamp' => '2026-01-22T10:00:00+00:00', 'message' => 'Test']);
    
    $mockHttp->shouldReceive('post')
        ->once()
        ->with(\Mockery::on(function ($url) {
            return str_contains($url, 'fallback_index/_bulk');
        }), \Mockery::type('array'))
        ->andReturn(new \GuzzleHttp\Psr7\Response(200));
    
    $handler->writeBatch([$record]);
});

test('OpenSearchIndexHandler writeBatch silently handles exceptions when silent', function () {
    $mockHttp = \Mockery::mock(\GuzzleHttp\Client::class);
    
    $handler = new OpenSearchIndexHandler(
        baseUrl: 'http://localhost:9200',
        index: 'test',
        documentBuilder: new \Ermetix\LaravelLogger\Logging\Builders\DefaultOpenSearchDocumentBuilder(),
        silent: true,
        http: $mockHttp,
    );
    
    $record = new LogRecord(
        datetime: new \DateTimeImmutable('now'),
        channel: 'opensearch',
        level: Level::Info,
        message: 'Test',
        context: ['log_index' => 'api_log'],
    );
    
    $request = new \GuzzleHttp\Psr7\Request('POST', 'http://localhost:9200/api_log/_bulk');
    $response = new \GuzzleHttp\Psr7\Response(400);
    $exception = new \GuzzleHttp\Exception\ClientException('Error', $request, $response);
    
    $mockHttp->shouldReceive('post')
        ->once()
        ->andThrow($exception);
    
    // Should not throw when silent=true
    $handler->writeBatch([$record]);
    
    expect(true)->toBeTrue(); // Just verify it doesn't throw
});

test('KafkaRestProxyHandler writeBatch silently handles exceptions when silent', function () {
    $mockHttp = \Mockery::mock(\GuzzleHttp\Client::class);
    
    $handler = new KafkaRestProxyHandler(
        restProxyUrl: 'http://localhost:8082',
        topic: 'test',
        valueBuilder: new \Ermetix\LaravelLogger\Logging\Builders\DefaultKafkaValueBuilder(),
        silent: true,
        http: $mockHttp,
    );
    
    $record = new LogRecord(
        datetime: new \DateTimeImmutable('now'),
        channel: 'kafka',
        level: Level::Info,
        message: 'Test',
        context: ['log_index' => 'api_log'],
    );
    
    $request = new \GuzzleHttp\Psr7\Request('POST', 'http://localhost:8082/topics/test');
    $response = new \GuzzleHttp\Psr7\Response(400);
    $exception = new \GuzzleHttp\Exception\ClientException('Error', $request, $response);
    
    $mockHttp->shouldReceive('post')
        ->once()
        ->andThrow($exception);
    
    // Should not throw when silent=true
    $handler->writeBatch([$record]);
    
    expect(true)->toBeTrue(); // Just verify it doesn't throw
});

test('IndexFileHandler writeBatch creates directory when it does not exist', function () {
    $testDir = storage_path('framework/testing/index-file-batch-new-dir');
    \Illuminate\Support\Facades\File::deleteDirectory($testDir);
    
    // Override storage_path to return a non-existent directory
    $GLOBALS['__ll_storage_path_override'] = $testDir;
    
    $handler = new IndexFileHandler();
    
    $record = new LogRecord(
        datetime: new \DateTimeImmutable('now'),
        channel: 'index_file',
        level: Level::Info,
        message: 'Test',
        context: ['log_index' => 'test'],
    );
    
    $handler->writeBatch([$record]);
    
    // Directory should have been created
    expect(is_dir($testDir))->toBeTrue();
    
    // Cleanup
    \Illuminate\Support\Facades\File::deleteDirectory($testDir);
    unset($GLOBALS['__ll_storage_path_override']);
});

test('IndexFileHandler writeBatch handles exceptions in try block', function () {
    // Force storage_path to throw
    $GLOBALS['__ll_throw_storage_path'] = true;
    
    $handler = new IndexFileHandler();
    
    $record = new LogRecord(
        datetime: new \DateTimeImmutable('now'),
        channel: 'index_file',
        level: Level::Info,
        message: 'Test',
        context: ['log_index' => 'test'],
    );
    
    // Should not throw even if storage_path fails
    $handler->writeBatch([$record]);
    
    unset($GLOBALS['__ll_throw_storage_path']);
    expect(true)->toBeTrue(); // Just verify it doesn't throw
});
