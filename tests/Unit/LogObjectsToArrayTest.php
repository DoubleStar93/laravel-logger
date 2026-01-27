<?php

use Ermetix\LaravelLogger\Support\Logging\Objects\ApiLogObject;
use Ermetix\LaravelLogger\Support\Logging\Objects\GeneralLogObject;
use Ermetix\LaravelLogger\Support\Logging\Objects\JobLogObject;
use Ermetix\LaravelLogger\Support\Logging\Objects\IntegrationLogObject;
use Ermetix\LaravelLogger\Support\Logging\Objects\OrmLogObject;
use Ermetix\LaravelLogger\Support\Logging\Objects\ErrorLogObject;

test('All LogObjects toArray excludes null values', function () {
    $apiLog = new ApiLogObject(message: 'test');
    $generalLog = new GeneralLogObject(message: 'test');
    $jobLog = new JobLogObject(message: 'test');
    $integrationLog = new IntegrationLogObject(message: 'test');
    $ormLog = new OrmLogObject(message: 'test');
    $errorLog = new ErrorLogObject(message: 'test');
    
    $apiArray = $apiLog->toArray();
    $generalArray = $generalLog->toArray();
    $jobArray = $jobLog->toArray();
    $integrationArray = $integrationLog->toArray();
    $ormArray = $ormLog->toArray();
    $errorArray = $errorLog->toArray();
    
    // None should contain null values
    expect($apiArray)->not->toContain(null);
    expect($generalArray)->not->toContain(null);
    expect($jobArray)->not->toContain(null);
    expect($integrationArray)->not->toContain(null);
    expect($ormArray)->not->toContain(null);
    expect($errorArray)->not->toContain(null);
});

test('ApiLogObject toArray includes all provided fields', function () {
    $log = new ApiLogObject(
        message: 'api_request',
        method: 'GET',
        path: '/api/test',
        routeName: 'test.route',
        status: 200,
        durationMs: 45,
        ip: '127.0.0.1',
        userId: '123',
        userAgent: 'Mozilla/5.0',
        referer: 'https://example.com',
        queryString: 'foo=bar',
        requestSizeBytes: 1024,
        responseSizeBytes: 2048,
        authenticationMethod: 'bearer',
        apiVersion: 'v1',
        correlationId: 'corr-123',
    );
    
    $array = $log->toArray();
    
    expect($array)->toHaveKey('method', 'GET');
    expect($array)->toHaveKey('path', '/api/test');
    expect($array)->toHaveKey('route_name', 'test.route');
    expect($array)->toHaveKey('status', 200);
    expect($array)->toHaveKey('duration_ms', 45);
    expect($array)->toHaveKey('ip', '127.0.0.1');
    expect($array)->toHaveKey('user_id', '123');
    expect($array)->toHaveKey('user_agent', 'Mozilla/5.0');
    expect($array)->toHaveKey('referer', 'https://example.com');
    expect($array)->toHaveKey('query_string', 'foo=bar');
    expect($array)->toHaveKey('request_size_bytes', 1024);
    expect($array)->toHaveKey('response_size_bytes', 2048);
    expect($array)->toHaveKey('authentication_method', 'bearer');
    expect($array)->toHaveKey('api_version', 'v1');
    expect($array)->toHaveKey('correlation_id', 'corr-123');
});

test('GeneralLogObject toArray includes message and source location', function () {
    $log = new GeneralLogObject(
        message: 'test_message',
        event: 'test_event',
        entityType: 'user',
        entityId: '123',
        actionType: 'create',
        userId: '456',
        level: 'info',
        file: '/app/test.php',
        line: 42,
        class: 'TestClass',
        function: 'testFunction',
    );
    
    $array = $log->toArray();
    
    expect($array)->toHaveKey('message', 'test_message');
    expect($array)->toHaveKey('event', 'test_event');
    expect($array)->toHaveKey('entity_type', 'user');
    expect($array)->toHaveKey('entity_id', '123');
    expect($array)->toHaveKey('action_type', 'create');
    expect($array)->toHaveKey('user_id', '456');
    expect($array)->toHaveKey('file', '/app/test.php');
    expect($array)->toHaveKey('line', 42);
    expect($array)->toHaveKey('class', 'TestClass');
    expect($array)->toHaveKey('function', 'testFunction');
});

test('JobLogObject toArray includes all job-specific fields', function () {
    $log = new JobLogObject(
        message: 'job_completed',
        job: 'test:job',
        jobId: 'job-123',
        queueName: 'default',
        attempts: 1,
        maxAttempts: 3,
        command: 'php artisan test:job',
        status: 'success',
        durationMs: 1000,
        exitCode: 0,
        memoryPeakMb: 128.5,
        frequency: '*/5 * * * *',
        output: 'Job completed successfully',
    );
    
    $array = $log->toArray();
    
    expect($array)->toHaveKey('job', 'test:job');
    expect($array)->toHaveKey('job_id', 'job-123');
    expect($array)->toHaveKey('queue_name', 'default');
    expect($array)->toHaveKey('attempts', 1);
    expect($array)->toHaveKey('max_attempts', 3);
    expect($array)->toHaveKey('command', 'php artisan test:job');
    expect($array)->toHaveKey('status', 'success');
    expect($array)->toHaveKey('duration_ms', 1000);
    expect($array)->toHaveKey('exit_code', 0);
    expect($array)->toHaveKey('memory_peak_mb', 128.5);
    expect($array)->toHaveKey('frequency', '*/5 * * * *');
    expect($array)->toHaveKey('output', 'Job completed successfully');
});

test('IntegrationLogObject toArray includes all integration-specific fields', function () {
    $log = new IntegrationLogObject(
        message: 'api_call',
        integrationName: 'stripe',
        url: 'https://api.stripe.com/v1/charges',
        method: 'POST',
        status: 200,
        durationMs: 350,
        externalId: 'ext_123',
        correlationId: 'corr-456',
        attempt: 1,
        maxAttempts: 3,
        requestSizeBytes: 512,
        responseSizeBytes: 1024,
        errorMessage: null,
    );
    
    $array = $log->toArray();
    
    expect($array)->toHaveKey('integration_name', 'stripe');
    expect($array)->toHaveKey('url', 'https://api.stripe.com/v1/charges');
    expect($array)->toHaveKey('method', 'POST');
    expect($array)->toHaveKey('status', 200);
    expect($array)->toHaveKey('duration_ms', 350);
    expect($array)->toHaveKey('external_id', 'ext_123');
    expect($array)->toHaveKey('correlation_id', 'corr-456');
    expect($array)->toHaveKey('attempt', 1);
    expect($array)->toHaveKey('max_attempts', 3);
    expect($array)->toHaveKey('request_size_bytes', 512);
    expect($array)->toHaveKey('response_size_bytes', 1024);
    expect($array)->not->toHaveKey('error_message'); // null values excluded
});

test('OrmLogObject toArray includes all ORM-specific fields', function () {
    $log = new OrmLogObject(
        message: 'database_query',
        model: 'User',
        modelId: '123',
        action: 'create',
        query: 'INSERT INTO users ...',
        queryType: 'INSERT',
        isSlowQuery: false,
        durationMs: 5,
        bindings: '["John","john@example.com"]',
        connection: 'mysql',
        table: 'users',
        transactionId: 'txn-789',
        userId: '456',
        previousValue: null,
        afterValue: ['name' => 'John', 'email' => 'john@example.com'],
    );
    
    $array = $log->toArray();
    
    expect($array)->toHaveKey('model', 'User');
    expect($array)->toHaveKey('model_id', '123');
    expect($array)->toHaveKey('action', 'create');
    expect($array)->toHaveKey('query', 'INSERT INTO users ...');
    expect($array)->toHaveKey('query_type', 'INSERT');
    expect($array)->toHaveKey('is_slow_query', false);
    expect($array)->toHaveKey('duration_ms', 5);
    expect($array)->toHaveKey('bindings', '["John","john@example.com"]');
    expect($array)->toHaveKey('connection', 'mysql');
    expect($array)->toHaveKey('table', 'users');
    expect($array)->toHaveKey('transaction_id', 'txn-789');
    expect($array)->toHaveKey('user_id', '456');
    expect($array)->not->toHaveKey('previous_value'); // null excluded
    expect($array)->toHaveKey('after_value', ['name' => 'John', 'email' => 'john@example.com']);
});

test('ErrorLogObject toArray includes all error-specific fields', function () {
    $log = new ErrorLogObject(
        message: 'error_occurred',
        stackTrace: '#0 /app/test.php(10): test()',
        exceptionClass: 'TestException',
        code: 500,
        previousException: ['message' => 'Previous error'],
        userId: '123',
        route: 'test.route',
        method: 'GET',
        url: 'http://localhost/api/test',
    );
    
    $array = $log->toArray();
    
    expect($array)->toHaveKey('stack_trace', '#0 /app/test.php(10): test()');
    expect($array)->toHaveKey('exception_class', 'TestException');
    expect($array)->toHaveKey('code', 500);
    expect($array)->toHaveKey('previous_exception', ['message' => 'Previous error']);
    expect($array)->toHaveKey('context_user_id', '123');
    expect($array)->toHaveKey('context_route', 'test.route');
    expect($array)->toHaveKey('context_method', 'GET');
    expect($array)->toHaveKey('context_url', 'http://localhost/api/test');
});
