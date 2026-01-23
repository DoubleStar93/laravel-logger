<?php

use Ermetix\LaravelLogger\Logging\Contracts\KafkaValueBuilder;
use Ermetix\LaravelLogger\Logging\Handlers\KafkaRestProxyHandler;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request as Psr7Request;
use Monolog\Level;
use Monolog\LogRecord;
use Psr\Http\Message\ResponseInterface;

test('KafkaRestProxyHandler posts to kafka rest proxy', function () {
    $client = \Mockery::mock(\GuzzleHttp\Client::class);
    $client->shouldReceive('post')
        ->once()
        ->withArgs(function (string $url, array $options): bool {
            expect($url)->toBe('http://localhost:8082/topics/laravel-logs');
            expect($options['headers']['Content-Type'])->toBe('application/vnd.kafka.json.v2+json');
            expect($options['json']['records'][0]['value'])->toBe(['k' => 'v']);
            return true;
        })
        ->andReturn(\Mockery::mock(ResponseInterface::class));

    $handler = new KafkaRestProxyHandler(
        restProxyUrl: 'http://localhost:8082',
        topic: 'laravel-logs',
        valueBuilder: new class implements KafkaValueBuilder {
            public function __invoke(LogRecord $record): array { return ['k' => 'v']; }
        },
        level: Level::Info,
        timeout: 1,
        silent: true,
        http: $client,
    );

    $record = new LogRecord(
        datetime: new DateTimeImmutable(),
        channel: 'kafka',
        level: Level::Info,
        message: 'm',
        context: [],
        extra: [],
    );

    $handler->handle($record);
});

test('KafkaRestProxyHandler swallows exceptions when silent', function () {
    $client = \Mockery::mock(\GuzzleHttp\Client::class);
    $client->shouldReceive('post')
        ->once()
        ->andThrow(new ConnectException('fail', new Psr7Request('POST', 'http://localhost')));

    $handler = new KafkaRestProxyHandler(
        restProxyUrl: 'http://localhost:8082',
        topic: 'laravel-logs',
        valueBuilder: new class implements KafkaValueBuilder {
            public function __invoke(LogRecord $record): array { return ['k' => 'v']; }
        },
        level: 'info',
        timeout: 1,
        silent: true,
        http: $client,
    );

    $handler->handle(new LogRecord(
        datetime: new DateTimeImmutable(),
        channel: 'kafka',
        level: Level::Info,
        message: 'm',
        context: [],
        extra: [],
    ));
});

test('KafkaRestProxyHandler rethrows exceptions when not silent', function () {
    $client = \Mockery::mock(\GuzzleHttp\Client::class);
    $client->shouldReceive('post')
        ->once()
        ->andThrow(new ConnectException('fail', new Psr7Request('POST', 'http://localhost')));

    $handler = new KafkaRestProxyHandler(
        restProxyUrl: 'http://localhost:8082',
        topic: 'laravel-logs',
        valueBuilder: new class implements KafkaValueBuilder {
            public function __invoke(LogRecord $record): array { return ['k' => 'v']; }
        },
        level: Level::Info,
        timeout: 1,
        silent: false,
        http: $client,
    );

    expect(fn () => $handler->handle(new LogRecord(
        datetime: new DateTimeImmutable(),
        channel: 'kafka',
        level: Level::Info,
        message: 'm',
        context: [],
        extra: [],
    )))->toThrow(ConnectException::class);
});

