<?php

use Ermetix\LaravelLogger\Jobs\LogToOpenSearch;
use Ermetix\LaravelLogger\Logging\Handlers\OpenSearchIndexHandler;
use Monolog\LogRecord;

test('LogToOpenSearch job builds LogRecord and delegates to OpenSearchIndexHandler', function () {
    $handler = \Mockery::mock(OpenSearchIndexHandler::class);
    $handler->shouldReceive('handle')
        ->once()
        ->with(\Mockery::on(function ($record) {
            expect($record)->toBeInstanceOf(LogRecord::class);
            expect($record->context['message'])->toBe('hello');
            expect($record->context['level'])->toBe('error');
            expect($record->context['@timestamp'])->toBe('2026-01-22T10:11:12.000000+00:00');
            return true;
        }));

    app()->bind(OpenSearchIndexHandler::class, function () use ($handler) {
        return $handler;
    });

    $job = new LogToOpenSearch(
        index: 'error_log',
        document: [
            '@timestamp' => '2026-01-22T10:11:12.000000+00:00',
            'level' => 'error',
            'message' => 'hello',
        ],
    );

    $job->handle();
});

test('LogToOpenSearch job uses current datetime when @timestamp is missing', function () {
    $handler = \Mockery::mock(OpenSearchIndexHandler::class);
    $handler->shouldReceive('handle')
        ->once()
        ->with(\Mockery::on(function (LogRecord $record) {
            // When missing @timestamp, the record datetime is generated at runtime.
            expect($record->datetime)->toBeInstanceOf(DateTimeImmutable::class);
            return true;
        }));

    app()->bind(OpenSearchIndexHandler::class, fn () => $handler);

    (new LogToOpenSearch(index: 'general_log', document: ['level' => 'info', 'message' => 'hello']))->handle();
});

