<?php

use Ermetix\LaravelLogger\Logging\Builders\IndexKeyKafkaValueBuilder;
use Monolog\Level;
use Monolog\LogRecord;

test('IndexKeyKafkaValueBuilder returns {index: document} and uses context only', function () {
    $builder = new IndexKeyKafkaValueBuilder();

    $record = new LogRecord(
        datetime: new DateTimeImmutable('2026-01-22T10:11:12+00:00'),
        channel: 'kafka',
        level: Level::Info,
        message: 'ignored_message',
        context: [
            'log_index' => 'api_log',
            'message' => 'api_request',
            'method' => 'GET',
            'path' => '/api/ping',
        ],
        extra: [
            'should_not' => 'appear',
        ],
    );

    $value = $builder($record);

    expect($value)->toBeArray();
    expect(array_keys($value))->toBe(['api_log']);

    $doc = $value['api_log'];
    expect($doc)->toBeArray();

    // routing helper removed
    expect($doc)->not->toHaveKey('log_index');

    // timestamp injected
    expect($doc)->toHaveKey('@timestamp');
    expect($doc['@timestamp'])->toBe('2026-01-22T10:11:12+00:00');

    // object fields preserved
    expect($doc['message'])->toBe('api_request');
    expect($doc['method'])->toBe('GET');
    expect($doc['path'])->toBe('/api/ping');

    // monolog metadata is not added by this builder
    expect($doc)->not->toHaveKey('channel');
    expect($doc)->not->toHaveKey('extra');
    expect($doc)->not->toHaveKey('context');
});

test('IndexKeyKafkaValueBuilder falls back to general_log when log_index is missing', function () {
    $builder = new IndexKeyKafkaValueBuilder();

    $record = new LogRecord(
        datetime: new DateTimeImmutable('2026-01-22T10:11:12+00:00'),
        channel: 'kafka',
        level: Level::Info,
        message: 'ignored_message',
        context: [
            'message' => 'hello',
        ],
        extra: [],
    );

    $value = $builder($record);

    expect(array_keys($value))->toBe(['general_log']);
    expect($value['general_log'])->toHaveKey('message', 'hello');
});

test('IndexKeyKafkaValueBuilder falls back to general_log when log_index is empty or not a string', function () {
    $builder = new IndexKeyKafkaValueBuilder();

    $recordEmpty = new LogRecord(
        datetime: new DateTimeImmutable('2026-01-22T10:11:12+00:00'),
        channel: 'kafka',
        level: Level::Info,
        message: 'ignored',
        context: ['log_index' => '', 'message' => 'x'],
        extra: [],
    );

    $recordNotString = new LogRecord(
        datetime: new DateTimeImmutable('2026-01-22T10:11:12+00:00'),
        channel: 'kafka',
        level: Level::Info,
        message: 'ignored',
        context: ['log_index' => ['nope'], 'message' => 'y'],
        extra: [],
    );

    expect(array_keys($builder($recordEmpty)))->toBe(['general_log']);
    expect(array_keys($builder($recordNotString)))->toBe(['general_log']);
});

