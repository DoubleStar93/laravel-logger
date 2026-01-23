<?php

use Ermetix\LaravelLogger\Support\Logging\Objects\GeneralLogObject;
use Ermetix\LaravelLogger\Support\Logging\Objects\ApiLogObject;
use Ermetix\LaravelLogger\Support\Logging\Objects\ErrorLogObject;

test('GeneralLogObject includes message and source location fields', function () {
    $log = new GeneralLogObject(
        message: 'test_message',
        event: 'test_event',
        level: 'info',
        file: '/app/test.php',
        line: 42,
        class: 'TestClass',
        function: 'testFunction',
    );
    
    $array = $log->toArray();
    
    // Should include message (general_log specific)
    expect($array)->toHaveKey('message', 'test_message');
    
    // Should include source location fields
    expect($array)->toHaveKey('file', '/app/test.php');
    expect($array)->toHaveKey('line', 42);
    expect($array)->toHaveKey('class', 'TestClass');
    expect($array)->toHaveKey('function', 'testFunction');
});

test('ApiLogObject excludes message and source location fields', function () {
    $log = new ApiLogObject(
        message: 'api_request',
        method: 'GET',
        path: '/api/test',
        level: 'info',
        file: '/app/middleware.php', // Should be excluded
        line: 10, // Should be excluded
        class: 'ApiAccessLog', // Should be excluded
        function: 'handle', // Should be excluded
    );
    
    $array = $log->toArray();
    
    // Should NOT include message
    expect($array)->not->toHaveKey('message');
    
    // Should NOT include source location fields
    expect($array)->not->toHaveKey('file');
    expect($array)->not->toHaveKey('line');
    expect($array)->not->toHaveKey('class');
    expect($array)->not->toHaveKey('function');
    
    // Should include API-specific fields
    expect($array)->toHaveKey('method', 'GET');
    expect($array)->toHaveKey('path', '/api/test');
});

test('ErrorLogObject excludes message and source location fields', function () {
    $log = new ErrorLogObject(
        message: 'error_occurred',
        exceptionClass: 'TestException',
        code: 500,
        level: 'error',
        file: '/app/error.php', // Should be excluded
        line: 20, // Should be excluded
        class: 'ErrorHandler', // Should be excluded
        function: 'handle', // Should be excluded
    );
    
    $array = $log->toArray();
    
    // Should NOT include message
    expect($array)->not->toHaveKey('message');
    
    // Should NOT include source location fields (stack_trace has all info)
    expect($array)->not->toHaveKey('file');
    expect($array)->not->toHaveKey('line');
    expect($array)->not->toHaveKey('class');
    expect($array)->not->toHaveKey('function');
    
    // Should include error-specific fields
    expect($array)->toHaveKey('exception_class', 'TestException');
    expect($array)->toHaveKey('code', 500);
});

test('BaseLogObject getCommonFields filters null values', function () {
    $log = new GeneralLogObject(
        message: 'test',
        level: 'info',
        // All optional fields are null
    );
    
    $array = $log->toArray();
    
    // Should not include null fields
    expect($array)->not->toHaveKey('parent_request_id');
    expect($array)->not->toHaveKey('trace_id');
    expect($array)->not->toHaveKey('span_id');
    expect($array)->not->toHaveKey('session_id');
    expect($array)->not->toHaveKey('hostname');
    expect($array)->not->toHaveKey('service_name');
    expect($array)->not->toHaveKey('app_version');
    expect($array)->not->toHaveKey('tags');
    
    // Should include non-null fields
    expect($array)->toHaveKey('level', 'info');
    expect($array)->toHaveKey('message', 'test');
});

test('BaseLogObject getCommonFields includes optional fields when provided', function () {
    $log = new GeneralLogObject(
        message: 'test',
        level: 'info',
        parentRequestId: 'parent-123',
        traceId: 'trace-456',
        spanId: 'span-789',
        sessionId: 'session-abc',
        hostname: 'server-01',
        serviceName: 'my-service',
        appVersion: '1.0.0',
        tags: ['tag1', 'tag2'],
    );
    
    $array = $log->toArray();
    
    // Should include all provided fields
    expect($array)->toHaveKey('parent_request_id', 'parent-123');
    expect($array)->toHaveKey('trace_id', 'trace-456');
    expect($array)->toHaveKey('span_id', 'span-789');
    expect($array)->toHaveKey('session_id', 'session-abc');
    expect($array)->toHaveKey('hostname', 'server-01');
    expect($array)->toHaveKey('service_name', 'my-service');
    expect($array)->toHaveKey('app_version', '1.0.0');
    expect($array)->toHaveKey('tags', ['tag1', 'tag2']);
});
