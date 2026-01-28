<?php

use Ermetix\LaravelLogger\Logging\Builders\DefaultOpenSearchDocumentBuilder;
use Monolog\Level;
use Monolog\LogRecord;

test('DefaultOpenSearchDocumentBuilder chooses index from context/extra with fallback', function () {
    $builder = new DefaultOpenSearchDocumentBuilder();

    $fromContext = new LogRecord(
        datetime: new DateTimeImmutable(),
        channel: 'opensearch',
        level: Level::Info,
        message: 'm',
        context: ['log_index' => 'api_log'],
        extra: [],
    );

    $fromExtra = new LogRecord(
        datetime: new DateTimeImmutable(),
        channel: 'opensearch',
        level: Level::Info,
        message: 'm',
        context: [],
        extra: ['log_index' => 'job_log'],
    );

    $fallback = new LogRecord(
        datetime: new DateTimeImmutable(),
        channel: 'opensearch',
        level: Level::Info,
        message: 'm',
        context: ['log_index' => ''],
        extra: [],
    );

    expect($builder->index($fromContext))->toBe('api_log');
    expect($builder->index($fromExtra))->toBe('job_log');
    expect($builder->index($fallback))->toBe('general_log');
});

test('DefaultOpenSearchDocumentBuilder builds document, excludes log_index, and populates common fields', function () {
    $builder = new DefaultOpenSearchDocumentBuilder();

    $record = new LogRecord(
        datetime: new DateTimeImmutable('2026-01-22T10:11:12+00:00'),
        channel: 'opensearch',
        level: Level::Warning,
        message: 'hello',
        context: [
            'log_index' => 'api_log',
            'method' => 'GET',
            'path' => '/api/ping',
            'request_id' => 'ctx-rid',
        ],
        extra: [
            'request_id' => 'extra-rid',
        ],
    );

    $doc = $builder->document($record);

    expect($doc)->toHaveKey('@timestamp', '2026-01-22T10:11:12.000000+00:00');
    expect($doc)->toHaveKey('level', 'warning');
    // message field is NOT included in the document by DefaultOpenSearchDocumentBuilder
    // It's only included by LogObject::toArray() for general_log
    // Since this is api_log, message should not be present
    expect($doc)->not->toHaveKey('message');

    // request_id prefers extra, falls back to context
    expect($doc)->toHaveKey('request_id', 'extra-rid');

    // context fields are flattened, but log_index is omitted
    expect($doc)->not->toHaveKey('log_index');
    expect($doc)->toHaveKey('method', 'GET');
    expect($doc)->toHaveKey('path', '/api/ping');

    // from config / environment
    expect($doc)->toHaveKey('environment');
    expect($doc)->toHaveKey('service_name', 'test-service');
    expect($doc)->toHaveKey('hostname');
});

test('DefaultOpenSearchDocumentBuilder ignores session errors while populating common fields', function () {
    $builder = new DefaultOpenSearchDocumentBuilder();

    $GLOBALS['__ll_throw_session'] = true;

    $record = new LogRecord(
        datetime: new DateTimeImmutable(),
        channel: 'opensearch',
        level: Level::Info,
        message: 'hello',
        context: ['log_index' => 'general_log'],
        extra: [],
    );

    $doc = $builder->document($record);

    expect($doc)->toBeArray();

    unset($GLOBALS['__ll_throw_session']);
});

test('DefaultOpenSearchDocumentBuilder uses app.name fallback and adds app_version when configured', function () {
    $builder = new DefaultOpenSearchDocumentBuilder();

    config([
        'laravel-logger.service_name' => null,
        'app.name' => 'my-app',
        'app.version' => '1.2.3',
    ]);

    $record = new LogRecord(
        datetime: new DateTimeImmutable(),
        channel: 'opensearch',
        level: Level::Info,
        message: 'hello',
        context: ['log_index' => 'general_log'],
        extra: [],
    );

    $doc = $builder->document($record);

    expect($doc)->toHaveKey('service_name', 'my-app');
    expect($doc)->toHaveKey('app_version', '1.2.3');
});

test('DefaultOpenSearchDocumentBuilder skips vendor/monolog frames when populating source location', function () {
    $builder = new DefaultOpenSearchDocumentBuilder();

    $record = new LogRecord(
        datetime: new DateTimeImmutable(),
        channel: 'opensearch',
        level: Level::Info,
        message: 'hello',
        context: ['log_index' => 'general_log'],
        extra: [],
    );

    $tmpDir = storage_path('framework/testing/vendor/monolog');
    if (!is_dir($tmpDir)) {
        mkdir($tmpDir, 0777, true);
    }
    $tmpFile = $tmpDir.DIRECTORY_SEPARATOR.'caller.php';
    file_put_contents($tmpFile, <<<'PHP'
<?php
/** @var \Ermetix\LaravelLogger\Logging\Builders\DefaultOpenSearchDocumentBuilder $builder */
/** @var \Monolog\LogRecord $record */
$GLOBALS['__ll_doc_from_vendor_frame'] = $builder->document($record);
PHP);

    $GLOBALS['__ll_doc_from_vendor_frame'] = null;
    $builderVar = $builder;
    $recordVar = $record;

    // Provide variables for the included file.
    $builder = $builderVar;
    $record = $recordVar;
    include $tmpFile;

    expect($GLOBALS['__ll_doc_from_vendor_frame'])->toBeArray();
    unset($GLOBALS['__ll_doc_from_vendor_frame']);
});

