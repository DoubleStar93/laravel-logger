<?php

use Ermetix\LaravelLogger\Logging\Builders\IndexKeyKafkaValueBuilder;
use Monolog\Level;
use Monolog\LogRecord;

test('IndexKeyKafkaValueBuilder builds value with index key', function () {
    $builder = new IndexKeyKafkaValueBuilder();

    $record = new LogRecord(
        datetime: new DateTimeImmutable('2026-01-22T10:11:12+00:00'),
        channel: 'kafka',
        level: Level::Info,
        message: 'test',
        context: [
            'log_index' => 'api_log',
            'method' => 'GET',
            'path' => '/api/test',
            'status' => 200,
        ],
        extra: [],
    );

    $value = $builder($record);

    expect($value)->toBeArray();
    expect($value)->toHaveKey('api_log');
    expect($value['api_log'])->toHaveKey('@timestamp', '2026-01-22T10:11:12.000000+00:00');
    expect($value['api_log'])->toHaveKey('method', 'GET');
    expect($value['api_log'])->toHaveKey('path', '/api/test');
    expect($value['api_log'])->toHaveKey('status', 200);
    expect($value['api_log'])->not->toHaveKey('log_index');
});

test('IndexKeyKafkaValueBuilder uses fallback index when log_index is missing', function () {
    $builder = new IndexKeyKafkaValueBuilder();

    $record = new LogRecord(
        datetime: new DateTimeImmutable(),
        channel: 'kafka',
        level: Level::Info,
        message: 'test',
        context: [
            'method' => 'GET',
        ],
        extra: [],
    );

    $value = $builder($record);

    expect($value)->toHaveKey('general_log');
    expect($value['general_log'])->toHaveKey('method', 'GET');
});

test('IndexKeyKafkaValueBuilder uses fallback when log_index is empty string', function () {
    $builder = new IndexKeyKafkaValueBuilder();

    $record = new LogRecord(
        datetime: new DateTimeImmutable(),
        channel: 'kafka',
        level: Level::Info,
        message: 'test',
        context: [
            'log_index' => '',
            'method' => 'GET',
        ],
        extra: [],
    );

    $value = $builder($record);

    expect($value)->toHaveKey('general_log');
});

test('IndexKeyKafkaValueBuilder uses fallback when log_index is not string', function () {
    $builder = new IndexKeyKafkaValueBuilder();

    $record = new LogRecord(
        datetime: new DateTimeImmutable(),
        channel: 'kafka',
        level: Level::Info,
        message: 'test',
        context: [
            'log_index' => 123, // Not a string
            'method' => 'GET',
        ],
        extra: [],
    );

    $value = $builder($record);

    expect($value)->toHaveKey('general_log');
});
