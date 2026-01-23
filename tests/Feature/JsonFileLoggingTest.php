<?php

use Ermetix\LaravelLogger\Facades\LaravelLogger;
use Ermetix\LaravelLogger\Logging\CreateIndexFileLogger;
use Ermetix\LaravelLogger\Support\Logging\DeferredLogger;
use Ermetix\LaravelLogger\Support\Logging\Objects\ApiLogObject;
use Ermetix\LaravelLogger\Support\Logging\Objects\GeneralLogObject;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $dir = storage_path('logs/laravel-logger');

    // Clean previous run
    if (is_dir($dir)) {
        File::deleteDirectory($dir);
    }

    config([
        'laravel-logger.index_file.retention_days' => 14,
    ]);

    // Ensure the index_file channel exists for tests.
    config([
        'logging.channels.index_file' => [
            'driver' => 'custom',
            'via' => CreateIndexFileLogger::class,
            'level' => 'debug',
        ],
    ]);
});

test('index_file writes only when present in stack', function () {
    // Not in stack -> no file
    config([
        'logging.channels.stack.channels' => ['single'],
        // Avoid creating laravel.log during the test
        'logging.channels.single' => ['driver' => 'null'],
    ]);

    LaravelLogger::general(new GeneralLogObject(
        message: 'hello_general',
        event: 'test_event',
        userId: '1',
        level: 'info',
    ));

    $dir = storage_path('logs/laravel-logger');
    $today = (new DateTimeImmutable('now'))->format('Y-m-d');

    // Default is deferred, so flush explicitly in this test.
    app(DeferredLogger::class)->flush();

    expect(file_exists($dir.DIRECTORY_SEPARATOR.'general_log-'.$today.'.jsonl'))->toBeFalse();

    // In stack -> file exists
    config(['logging.channels.stack.channels' => ['index_file']]);

    LaravelLogger::api(new ApiLogObject(
        message: 'hello_api',
        method: 'GET',
        path: '/api/ping',
        status: 200,
        durationMs: 10,
        level: 'info',
    ));

    app(DeferredLogger::class)->flush();

    expect(file_exists($dir.DIRECTORY_SEPARATOR.'api_log-'.$today.'.jsonl'))->toBeTrue();
});

test('retention deletes files older than N days', function () {
    $dir = storage_path('logs/laravel-logger');

    // Keep only today (N=1)
    config(['laravel-logger.index_file.retention_days' => 1]);

    $today = (new DateTimeImmutable('now'))->format('Y-m-d');
    $old = 'api_log-2000-01-01.jsonl';
    $recent = 'api_log-'.$today.'.jsonl';

    File::ensureDirectoryExists($dir);
    file_put_contents($dir.DIRECTORY_SEPARATOR.$old, '{"old":true}'."\n");
    file_put_contents($dir.DIRECTORY_SEPARATOR.$recent, '{"recent":true}'."\n");

    config(['logging.channels.stack.channels' => ['index_file']]);

    // Trigger pruning by logging once through the index_file channel.
    LaravelLogger::api(new ApiLogObject(
        message: 'trigger_prune',
        method: 'GET',
        path: '/api/ping',
        status: 200,
        level: 'info',
    ));

    app(DeferredLogger::class)->flush();

    expect(file_exists($dir.DIRECTORY_SEPARATOR.$old))->toBeFalse();
    expect(file_exists($dir.DIRECTORY_SEPARATOR.$recent))->toBeTrue();
});

