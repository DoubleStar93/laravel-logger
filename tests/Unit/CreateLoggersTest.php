<?php

use Ermetix\LaravelLogger\Logging\Contracts\KafkaValueBuilder;
use Ermetix\LaravelLogger\Logging\Contracts\OpenSearchDocumentBuilder;
use Ermetix\LaravelLogger\Logging\CreateKafkaLogger;
use Ermetix\LaravelLogger\Logging\CreateOpenSearchLogger;
use Ermetix\LaravelLogger\Logging\Handlers\KafkaRestProxyHandler;
use Ermetix\LaravelLogger\Logging\Handlers\OpenSearchIndexHandler;
use Monolog\LogRecord;

class TestKafkaValueBuilder implements KafkaValueBuilder {
    public function __invoke(LogRecord $record): array { return ['ok' => true]; }
}

class TestOpenSearchBuilder implements OpenSearchDocumentBuilder {
    public function index(LogRecord $record): string { return 'general_log'; }
    public function document(LogRecord $record): array { return ['message' => 'x']; }
}

test('CreateKafkaLogger builds monolog logger with KafkaRestProxyHandler', function () {
    app()->singleton(TestKafkaValueBuilder::class);

    $factory = new CreateKafkaLogger();
    $logger = $factory([
        'rest_proxy_url' => 'http://localhost:8082',
        'topic' => 'laravel-logs',
        'timeout' => 1,
        'level' => 'info',
        'silent' => true,
        'value_builder' => TestKafkaValueBuilder::class,
    ]);

    expect($logger->getName())->toBe('kafka');
    expect($logger->getHandlers()[0])->toBeInstanceOf(KafkaRestProxyHandler::class);
});

test('CreateOpenSearchLogger builds monolog logger with OpenSearchIndexHandler', function () {
    app()->singleton(TestOpenSearchBuilder::class);

    $factory = new CreateOpenSearchLogger();
    $logger = $factory([
        'url' => 'http://localhost:9200',
        'index' => 'general_log',
        'timeout' => 1,
        'level' => 'info',
        'silent' => true,
        'verify_tls' => false,
        'document_builder' => TestOpenSearchBuilder::class,
    ]);

    expect($logger->getName())->toBe('opensearch');
    expect($logger->getHandlers()[0])->toBeInstanceOf(OpenSearchIndexHandler::class);
});

