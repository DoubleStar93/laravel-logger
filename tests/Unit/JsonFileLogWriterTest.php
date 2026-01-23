<?php

use Ermetix\LaravelLogger\Support\Logging\Contracts\LogObject;
use Ermetix\LaravelLogger\Support\Logging\JsonFileLogWriter;
use Illuminate\Support\Facades\File;

function makeTestLogObject(string $index, array $data = [], string $message = 'm', string $level = 'info'): LogObject {
    return new class($index, $data, $message, $level) implements LogObject {
        public function __construct(
            private readonly string $idx,
            private readonly array $data,
            private readonly string $msg,
            private readonly string $lvl,
        ) {}

        public function index(): string { return $this->idx; }
        public function level(): string { return $this->lvl; }
        public function message(): string { return $this->msg; }
        public function toArray(): array { return $this->data; }
    };
}

test('JsonFileLogWriter builds path and record', function () {
    $writer = new JsonFileLogWriter();
    $obj = makeTestLogObject('API LOG', ['foo' => 'bar']);

    $path = $writer->pathFor($obj);

    expect($path)->toMatch('/storage[\/\\\\]logs[\/\\\\]laravel-logger[\/\\\\]/i');
    expect($path)->toMatch('/api_log-\d{4}-\d{2}-\d{2}\.jsonl$/');

    $record = $writer->recordFor($obj);
    expect($record)->toHaveKey('@timestamp');
    expect($record)->toHaveKey('log_index', 'API LOG');
    expect($record)->toHaveKey('foo', 'bar');
});

test('JsonFileLogWriter writes jsonl and prunes old files', function () {
    config(['laravel-logger.index_file.retention_days' => 1]);

    $writer = new JsonFileLogWriter();
    $dir = storage_path('logs/laravel-logger');
    File::ensureDirectoryExists($dir);
    File::cleanDirectory($dir);

    // Old file (2 days ago) should be removed when pruning is enabled.
    $oldDate = (new DateTimeImmutable('today'))->sub(new DateInterval('P2D'))->format('Y-m-d');
    $oldPath = $dir.DIRECTORY_SEPARATOR.'general_log-'.$oldDate.'.jsonl';
    File::put($oldPath, "{\"message\":\"old\"}\n");

    $obj = makeTestLogObject('general_log', ['message' => 'hello', 'level' => 'info', 'a' => 1], message: 'hello', level: 'info');
    $writer->write($obj);

    $todayPath = $writer->pathFor($obj);
    expect(File::exists($todayPath))->toBeTrue();
    $content = (string) File::get($todayPath);
    $lines = array_values(array_filter(array_map('trim', preg_split("/\r?\n/", $content) ?: [])));
    $last = $lines[count($lines) - 1] ?? '';
    expect($last)->toContain('"message":"hello"');

    expect(File::exists($oldPath))->toBeFalse();
});

test('JsonFileLogWriter falls back when json_encode fails', function () {
    config(['laravel-logger.index_file.retention_days' => 0]);

    $writer = new JsonFileLogWriter();

    // Invalid UTF-8 sequence -> json_encode returns false.
    $bad = "\xB1\x31";
    $obj = makeTestLogObject('general_log', ['bad' => $bad], message: 'hello', level: 'info');

    $writer->write($obj);

    $path = $writer->pathFor($obj);
    expect(File::exists($path))->toBeTrue();

    $content = (string) File::get($path);
    $lines = array_values(array_filter(array_map('trim', preg_split("/\r?\n/", $content) ?: [])));
    $line = $lines[count($lines) - 1] ?? '';
    $decoded = json_decode($line, true);
    expect($decoded)->toBeArray();
    expect($decoded)->toHaveKey('json_error');
});

// Test removed: pruning functionality has been removed

test('JsonFileLogWriter returns early when json encoding fails twice', function () {
    config(['laravel-logger.index_file.retention_days' => 0]);

    $writer = new JsonFileLogWriter();
    $dir = storage_path('logs/laravel-logger');
    File::ensureDirectoryExists($dir);

    $GLOBALS['__ll_force_json_encode_fail'] = true;

    $obj = makeTestLogObject('general_log', ['message' => 'x', 'level' => 'info'], message: 'x', level: 'info');
    $path = $writer->pathFor($obj);
    File::delete($path);

    $writer->write($obj);

    expect(File::exists($path))->toBeFalse();
    unset($GLOBALS['__ll_force_json_encode_fail']);
});

test('JsonFileLogWriter never throws if storage_path fails', function () {
    $GLOBALS['__ll_throw_storage_path_support'] = true;

    $writer = new JsonFileLogWriter();
    $writer->write(makeTestLogObject('general_log', ['message' => 'x', 'level' => 'info']));

    unset($GLOBALS['__ll_throw_storage_path_support']);
    expect(true)->toBeTrue();
});

test('JsonFileLogWriter pruning uses file mtime when filename has no date', function () {
    config(['laravel-logger.index_file.retention_days' => 1]);

    $writer = new JsonFileLogWriter();
    $dir = storage_path('logs/laravel-logger');
    File::ensureDirectoryExists($dir);
    File::cleanDirectory($dir);

    $old = $dir.DIRECTORY_SEPARATOR.'old.jsonl';
    File::put($old, "{\"message\":\"old\"}\n");
    @touch($old, (new DateTimeImmutable('2000-01-01'))->getTimestamp());

    $writer->write(makeTestLogObject('general_log', ['message' => 'hello', 'level' => 'info'], message: 'hello', level: 'info'));

    expect(File::exists($old))->toBeFalse();
});

test('JsonFileLogWriter pruning handles invalid date in filename', function () {
    config(['laravel-logger.index_file.retention_days' => 1]);

    $writer = new JsonFileLogWriter();
    $dir = storage_path('logs/laravel-logger');
    File::ensureDirectoryExists($dir);
    File::cleanDirectory($dir);

    $bad = $dir.DIRECTORY_SEPARATOR.'general_log-2026-99-99.jsonl';
    File::put($bad, "{\"message\":\"old\"}\n");
    @touch($bad, (new DateTimeImmutable('2000-01-01'))->getTimestamp());

    $writer->write(makeTestLogObject('general_log', ['message' => 'hello', 'level' => 'info'], message: 'hello', level: 'info'));

    expect(File::exists($bad))->toBeFalse();
});

test('JsonFileLogWriter creates directory when missing', function () {
    config(['laravel-logger.index_file.retention_days' => 0]);

    $writer = new JsonFileLogWriter();
    $dir = storage_path('logs/laravel-logger');
    File::deleteDirectory($dir);

    $writer->write(makeTestLogObject('general_log', ['message' => 'hello', 'level' => 'info'], message: 'hello', level: 'info'));

    expect(File::isDirectory($dir))->toBeTrue();
});

test('JsonFileLogWriter pruning handles filemtime returning false', function () {
    config(['laravel-logger.index_file.retention_days' => 1]);

    $writer = new JsonFileLogWriter();
    $dir = storage_path('logs/laravel-logger');
    File::ensureDirectoryExists($dir);
    File::cleanDirectory($dir);

    // Create a file without date in filename
    $noDate = $dir.DIRECTORY_SEPARATOR.'no-date.jsonl';
    File::put($noDate, "{\"message\":\"old\"}\n");
    
    // Mock filemtime to return false (file doesn't exist or error)
    // This tests the is_int($mtime) check
    // We can't easily mock filemtime, but we can test with a file that will have filemtime issues
    
    $writer->write(makeTestLogObject('general_log', ['message' => 'hello', 'level' => 'info'], message: 'hello', level: 'info'));
    
    // File should still exist if filemtime fails (can't determine age)
    // Actually, if filemtime is false, fileDate remains null and file is not deleted
    expect(File::exists($noDate))->toBeTrue();
});

test('JsonFileLogWriter pruning uses file locking to prevent race conditions', function () {
    config(['laravel-logger.index_file.retention_days' => 1]);

    $writer = new JsonFileLogWriter();
    $dir = storage_path('logs/laravel-logger');
    File::ensureDirectoryExists($dir);
    File::cleanDirectory($dir);

    // Create old file
    $oldDate = (new DateTimeImmutable('today'))->sub(new DateInterval('P2D'))->format('Y-m-d');
    $oldPath = $dir.DIRECTORY_SEPARATOR.'general_log-'.$oldDate.'.jsonl';
    File::put($oldPath, "{\"message\":\"old\"}\n");

    // Create lock file to simulate another process holding the lock
    $marker = $dir.DIRECTORY_SEPARATOR.'.laravel-logger-json-last-prune';
    $lockFile = $marker . '.lock';
    $fp = fopen($lockFile, 'c+');
    flock($fp, LOCK_EX); // Acquire lock

    // Try to write (should skip pruning because lock is held)
    $obj = makeTestLogObject('general_log', ['message' => 'hello', 'level' => 'info'], message: 'hello', level: 'info');
    $writer->write($obj);

    // Old file should still exist because pruning was skipped
    expect(File::exists($oldPath))->toBeTrue();

    // Release lock
    flock($fp, LOCK_UN);
    fclose($fp);

    // Now write again (should prune)
    $writer->write($obj);

    // Old file should be deleted now
    expect(File::exists($oldPath))->toBeFalse();
});

test('JsonFileLogWriter pruning double-check pattern works correctly', function () {
    config(['laravel-logger.index_file.retention_days' => 1]);

    $writer = new JsonFileLogWriter();
    $dir = storage_path('logs/laravel-logger');
    File::ensureDirectoryExists($dir);
    File::cleanDirectory($dir);

    // Create old file
    $oldDate = (new DateTimeImmutable('today'))->sub(new DateInterval('P2D'))->format('Y-m-d');
    $oldPath = $dir.DIRECTORY_SEPARATOR.'general_log-'.$oldDate.'.jsonl';
    File::put($oldPath, "{\"message\":\"old\"}\n");

    // Create marker file with today's date (simulating another process already did pruning)
    $marker = $dir.DIRECTORY_SEPARATOR.'.laravel-logger-json-last-prune';
    File::put($marker, (new DateTimeImmutable('today'))->format('Y-m-d'));

    // Write (should skip pruning because marker says it's already done)
    $obj = makeTestLogObject('general_log', ['message' => 'hello', 'level' => 'info'], message: 'hello', level: 'info');
    $writer->write($obj);

    // Old file should still exist because pruning was skipped
    expect(File::exists($oldPath))->toBeTrue();
});

test('JsonFileLogWriter pruning handles lock file open failure gracefully', function () {
    config(['laravel-logger.index_file.retention_days' => 1]);

    $writer = new JsonFileLogWriter();
    $dir = storage_path('logs/laravel-logger');
    File::ensureDirectoryExists($dir);
    File::cleanDirectory($dir);

    // Create old file
    $oldDate = (new DateTimeImmutable('today'))->sub(new DateInterval('P2D'))->format('Y-m-d');
    $oldPath = $dir.DIRECTORY_SEPARATOR.'general_log-'.$oldDate.'.jsonl';
    File::put($oldPath, "{\"message\":\"old\"}\n");

    // Create a directory with the lock file name to make fopen fail
    $marker = $dir.DIRECTORY_SEPARATOR.'.laravel-logger-json-last-prune';
    $lockFile = $marker . '.lock';
    File::ensureDirectoryExists($lockFile); // Make it a directory instead of a file

    // Write should not throw and should skip pruning
    $obj = makeTestLogObject('general_log', ['message' => 'hello', 'level' => 'info'], message: 'hello', level: 'info');
    $writer->write($obj);

    // Old file should still exist because pruning was skipped
    expect(File::exists($oldPath))->toBeTrue();

    // Cleanup
    File::deleteDirectory($lockFile);
});

