<?php

use Ermetix\LaravelLogger\Logging\Handlers\IndexFileHandler;
use Illuminate\Support\Facades\File;
use Monolog\Level;
use Monolog\LogRecord;

function makeIndexFileRecord(array $context, string $message = 'msg'): LogRecord {
    return new LogRecord(
        datetime: new DateTimeImmutable('2026-01-22T10:11:12+00:00'),
        channel: 'index_file',
        level: Level::Info,
        message: $message,
        context: $context,
        extra: [],
    );
}

test('IndexFileHandler writes message when missing from context and sanitizes index', function () {
    $dir = storage_path('framework/testing/index-file');
    File::deleteDirectory($dir);
    File::ensureDirectoryExists($dir);
    $GLOBALS['__ll_storage_path_override'] = $dir;

    config(['laravel-logger.index_file.retention_days' => 0]);

    $handler = new IndexFileHandler(level: Level::Info);

    $record = makeIndexFileRecord([
        'log_index' => 'API LOG', // should become api_log in filename
        'path' => '/api/ping',
    ], message: 'hello-from-record');

    $handler->handle($record);

    $expected = $dir.DIRECTORY_SEPARATOR.'api_log-2026-01-22.jsonl';
    expect(File::exists($expected))->toBeTrue();

    $line = trim((string) File::get($expected));
    $doc = json_decode($line, true);
    expect($doc)->toBeArray();
    expect($doc)->toHaveKey('log_index', 'API LOG');
    expect($doc)->toHaveKey('message', 'hello-from-record');

    unset($GLOBALS['__ll_storage_path_override']);
});

test('IndexFileHandler falls back to general_log when log_index is invalid', function () {
    $dir = storage_path('framework/testing/index-file-invalid');
    File::deleteDirectory($dir);
    File::ensureDirectoryExists($dir);
    $GLOBALS['__ll_storage_path_override'] = $dir;

    config(['laravel-logger.index_file.retention_days' => 0]);

    $handler = new IndexFileHandler(level: 'info');
    $handler->handle(makeIndexFileRecord(['log_index' => ['nope'], 'message' => 'x']));

    expect(File::exists($dir.DIRECTORY_SEPARATOR.'general_log-2026-01-22.jsonl'))->toBeTrue();

    unset($GLOBALS['__ll_storage_path_override']);
});

test('IndexFileHandler returns early when json_encode fails', function () {
    $dir = storage_path('framework/testing/index-file-jsonfail');
    File::deleteDirectory($dir);
    File::ensureDirectoryExists($dir);
    $GLOBALS['__ll_storage_path_override'] = $dir;

    config(['laravel-logger.index_file.retention_days' => 0]);

    $handler = new IndexFileHandler();

    // invalid UTF-8 causes json_encode to fail
    $handler->handle(makeIndexFileRecord(['log_index' => 'general_log', 'bad' => "\xB1\x31"]));

    expect(File::exists($dir.DIRECTORY_SEPARATOR.'general_log-2026-01-22.jsonl'))->toBeFalse();

    unset($GLOBALS['__ll_storage_path_override']);
});

test('IndexFileHandler pruning is throttled by marker file', function () {
    $dir = storage_path('framework/testing/index-file-prune');
    File::deleteDirectory($dir);
    File::ensureDirectoryExists($dir);
    $GLOBALS['__ll_storage_path_override'] = $dir;

    config(['laravel-logger.index_file.retention_days' => 1]);

    $oldPath = $dir.DIRECTORY_SEPARATOR.'general_log-2026-01-01.jsonl';
    File::put($oldPath, "{\"message\":\"old\"}\n");

    // marker says pruning already done today -> should not prune
    $marker = $dir.DIRECTORY_SEPARATOR.'.laravel-logger-index-file-last-prune';
    File::put($marker, (new DateTimeImmutable('today'))->format('Y-m-d'));

    $handler = new IndexFileHandler();
    $handler->handle(makeIndexFileRecord(['log_index' => 'general_log', 'message' => 'hello']));

    expect(File::exists($oldPath))->toBeTrue();

    unset($GLOBALS['__ll_storage_path_override']);
});

test('IndexFileHandler pruning skips files without valid dates and deletes old dated files', function () {
    $dir = storage_path('framework/testing/index-file-prune-dates');
    File::deleteDirectory($dir);
    File::ensureDirectoryExists($dir);
    $GLOBALS['__ll_storage_path_override'] = $dir;

    config(['laravel-logger.index_file.retention_days' => 1]);

    $noDate = $dir.DIRECTORY_SEPARATOR.'no-date.jsonl';
    File::put($noDate, "{\"x\":1}\n");

    $invalidDate = $dir.DIRECTORY_SEPARATOR.'general_log-2026-99-99.jsonl';
    File::put($invalidDate, "{\"x\":1}\n");

    $oldValid = $dir.DIRECTORY_SEPARATOR.'general_log-2000-01-01.jsonl';
    File::put($oldValid, "{\"x\":1}\n");

    // Ensure no marker blocks pruning
    File::delete($dir.DIRECTORY_SEPARATOR.'.laravel-logger-index-file-last-prune');

    (new IndexFileHandler())->handle(makeIndexFileRecord(['log_index' => 'general_log', 'message' => 'hello']));

    expect(File::exists($noDate))->toBeTrue();
    expect(File::exists($invalidDate))->toBeTrue();
    expect(File::exists($oldValid))->toBeFalse();

    unset($GLOBALS['__ll_storage_path_override']);
});

test('IndexFileHandler never throws even if storage_path fails', function () {
    $GLOBALS['__ll_throw_storage_path'] = true;

    $handler = new IndexFileHandler();
    $handler->handle(makeIndexFileRecord(['log_index' => 'general_log', 'message' => 'hello']));

    unset($GLOBALS['__ll_throw_storage_path']);
    expect(true)->toBeTrue();
});

test('IndexFileHandler pruning uses file locking to prevent race conditions', function () {
    $dir = storage_path('framework/testing/index-file-lock');
    File::deleteDirectory($dir);
    File::ensureDirectoryExists($dir);
    $GLOBALS['__ll_storage_path_override'] = $dir;

    config(['laravel-logger.index_file.retention_days' => 1]);

    // Create old file
    $oldPath = $dir.DIRECTORY_SEPARATOR.'general_log-2000-01-01.jsonl';
    File::put($oldPath, "{\"message\":\"old\"}\n");

    // Create lock file to simulate another process holding the lock
    $marker = $dir.DIRECTORY_SEPARATOR.'.laravel-logger-index-file-last-prune';
    $lockFile = $marker . '.lock';
    $fp = fopen($lockFile, 'c+');
    flock($fp, LOCK_EX); // Acquire lock

    // Try to write (should skip pruning because lock is held)
    $handler = new IndexFileHandler();
    $handler->handle(makeIndexFileRecord(['log_index' => 'general_log', 'message' => 'hello']));

    // Old file should still exist because pruning was skipped
    expect(File::exists($oldPath))->toBeTrue();

    // Release lock
    flock($fp, LOCK_UN);
    fclose($fp);

    // Now write again (should prune)
    $handler->handle(makeIndexFileRecord(['log_index' => 'general_log', 'message' => 'hello']));

    // Old file should be deleted now
    expect(File::exists($oldPath))->toBeFalse();

    unset($GLOBALS['__ll_storage_path_override']);
});

test('IndexFileHandler pruning double-check pattern works correctly', function () {
    $dir = storage_path('framework/testing/index-file-double-check');
    File::deleteDirectory($dir);
    File::ensureDirectoryExists($dir);
    $GLOBALS['__ll_storage_path_override'] = $dir;

    config(['laravel-logger.index_file.retention_days' => 1]);

    // Create old file
    $oldPath = $dir.DIRECTORY_SEPARATOR.'general_log-2000-01-01.jsonl';
    File::put($oldPath, "{\"message\":\"old\"}\n");

    // Create marker file with today's date (simulating another process already did pruning)
    $marker = $dir.DIRECTORY_SEPARATOR.'.laravel-logger-index-file-last-prune';
    File::put($marker, (new DateTimeImmutable('today'))->format('Y-m-d'));

    // Write (should skip pruning because marker says it's already done)
    $handler = new IndexFileHandler();
    $handler->handle(makeIndexFileRecord(['log_index' => 'general_log', 'message' => 'hello']));

    // Old file should still exist because pruning was skipped
    expect(File::exists($oldPath))->toBeTrue();

    unset($GLOBALS['__ll_storage_path_override']);
});

test('IndexFileHandler pruning handles lock file open failure gracefully', function () {
    $dir = storage_path('framework/testing/index-file-lock-fail');
    File::deleteDirectory($dir);
    File::ensureDirectoryExists($dir);
    $GLOBALS['__ll_storage_path_override'] = $dir;

    config(['laravel-logger.index_file.retention_days' => 1]);

    // Create old file
    $oldPath = $dir.DIRECTORY_SEPARATOR.'general_log-2000-01-01.jsonl';
    File::put($oldPath, "{\"message\":\"old\"}\n");

    // Create a directory with the lock file name to make fopen fail
    $marker = $dir.DIRECTORY_SEPARATOR.'.laravel-logger-index-file-last-prune';
    $lockFile = $marker . '.lock';
    File::ensureDirectoryExists($lockFile); // Make it a directory instead of a file

    // Write should not throw and should skip pruning
    $handler = new IndexFileHandler();
    $handler->handle(makeIndexFileRecord(['log_index' => 'general_log', 'message' => 'hello']));

    // Old file should still exist because pruning was skipped
    expect(File::exists($oldPath))->toBeTrue();

    // Cleanup
    File::deleteDirectory($lockFile);
    unset($GLOBALS['__ll_storage_path_override']);
});

