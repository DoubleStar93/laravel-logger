<?php

use Ermetix\LaravelLogger\Support\Logging\Objects\ApiLogObject;
use Ermetix\LaravelLogger\Support\Logging\Objects\IntegrationLogObject;

test('BaseLogObject formatJsonIfValid formats valid JSON with pretty printing', function () {
    $log = new ApiLogObject(
        message: 'test',
        requestBody: '{"name":"John","age":30}',
        responseBody: '{"id":123,"status":"ok"}',
    );
    
    $array = $log->toArray();
    
    // Check that JSON is formatted (contains newlines/indentation)
    expect($array['request_body'])->toContain("\n");
    expect($array['request_body'])->toContain('"name"');
    expect($array['request_body'])->toContain('"John"');
    
    // Verify it's still valid JSON
    $decoded = json_decode($array['request_body'], true);
    expect($decoded)->toBeArray();
    expect($decoded['name'])->toBe('John');
    expect($decoded['age'])->toBe(30);
});

test('BaseLogObject formatJsonIfValid returns non-JSON strings as-is', function () {
    $log = new ApiLogObject(
        message: 'test',
        requestBody: 'This is not JSON, just plain text',
    );
    
    $array = $log->toArray();
    
    expect($array['request_body'])->toBe('This is not JSON, just plain text');
});

test('BaseLogObject formatJsonIfValid handles null values', function () {
    $log = new ApiLogObject(
        message: 'test',
        requestBody: null,
        responseBody: null,
    );
    
    $array = $log->toArray();
    
    expect($array)->not->toHaveKey('request_body');
    expect($array)->not->toHaveKey('response_body');
});

test('BaseLogObject formatJsonIfValid handles empty strings', function () {
    $log = new ApiLogObject(
        message: 'test',
        requestBody: '',
    );
    
    $array = $log->toArray();
    
    expect($array)->not->toHaveKey('request_body');
});

test('BaseLogObject formatArrayAsJson formats arrays with pretty printing', function () {
    $log = new ApiLogObject(
        message: 'test',
        requestHeaders: [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Authorization' => 'Bearer token123',
        ],
    );
    
    $array = $log->toArray();
    
    // Check that JSON is formatted
    expect($array['request_headers'])->toContain("\n");
    expect($array['request_headers'])->toContain('"Content-Type"');
    expect($array['request_headers'])->toContain('"application/json"');
    
    // Verify it's valid JSON
    $decoded = json_decode($array['request_headers'], true);
    expect($decoded)->toBeArray();
    expect($decoded['Content-Type'])->toBe('application/json');
});

test('BaseLogObject formatArrayAsJson handles null arrays', function () {
    $log = new ApiLogObject(
        message: 'test',
        requestHeaders: null,
    );
    
    $array = $log->toArray();
    
    expect($array)->not->toHaveKey('request_headers');
});

test('BaseLogObject formatArrayAsJson handles empty arrays', function () {
    $log = new ApiLogObject(
        message: 'test',
        requestHeaders: [],
    );
    
    $array = $log->toArray();
    
    expect($array)->not->toHaveKey('request_headers');
});

test('BaseLogObject formatJsonIfValid preserves Unicode characters', function () {
    $log = new ApiLogObject(
        message: 'test',
        requestBody: '{"message":"Ciao mondo! 🚀","name":"José"}',
    );
    
    $array = $log->toArray();
    
    // Unicode should not be escaped
    expect($array['request_body'])->toContain('🚀');
    expect($array['request_body'])->toContain('José');
    expect($array['request_body'])->not->toContain('\\u');
});

test('BaseLogObject formatJsonIfValid preserves forward slashes', function () {
    $log = new ApiLogObject(
        message: 'test',
        requestBody: '{"url":"https://example.com/api/test"}',
    );
    
    $array = $log->toArray();
    
    // Forward slashes should not be escaped
    expect($array['request_body'])->toContain('https://example.com/api/test');
    expect($array['request_body'])->not->toContain('\\/');
});

test('IntegrationLogObject formats headers with pretty printing', function () {
    $log = new IntegrationLogObject(
        message: 'test',
        headers: [
            'X-API-Key' => 'secret-key',
            'Content-Type' => 'application/json',
        ],
    );
    
    $array = $log->toArray();
    
    expect($array['headers'])->toContain("\n");
    expect($array['headers'])->toContain('"X-API-Key"');
    expect($array['headers'])->toContain('"secret-key"');
    
    // Verify it's valid JSON
    $decoded = json_decode($array['headers'], true);
    expect($decoded)->toBeArray();
    expect($decoded['X-API-Key'])->toBe('secret-key');
});
