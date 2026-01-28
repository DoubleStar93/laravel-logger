<?php

use Ermetix\LaravelLogger\Logging\Builders\DefaultKafkaValueBuilder;
use Monolog\Level;
use Monolog\LogRecord;

test('DefaultKafkaValueBuilder includes monolog metadata', function () {
    $builder = new DefaultKafkaValueBuilder();

    $record = new LogRecord(
        datetime: new DateTimeImmutable('2026-01-22T10:11:12+00:00'),
        channel: 'test',
        level: Level::Warning,
        message: 'hello',
        context: ['foo' => 'bar'],
        extra: ['request_id' => 'rid'],
    );

    $value = $builder($record);

    expect($value)->toMatchArray([
        'timestamp' => '2026-01-22T10:11:12.000000+00:00',
        'level' => 'WARNING',
        'channel' => 'test',
        'message' => 'hello',
        'context' => ['foo' => 'bar'],
        'extra' => ['request_id' => 'rid'],
    ]);
});

