<?php

use Ermetix\LaravelLogger\Support\Logging\Objects\ApiLogObject;
use Ermetix\LaravelLogger\Support\Logging\Objects\IntegrationLogObject;

test('ApiLogObject formats request_body and response_body as pretty JSON', function () {
    $log = new ApiLogObject(
        message: 'api_request',
        method: 'POST',
        path: '/api/users',
        requestBody: '{"name":"John","email":"john@example.com"}',
        responseBody: '{"id":123,"name":"John","email":"john@example.com"}',
        requestHeaders: ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
        responseHeaders: ['Content-Type' => 'application/json'],
    );
    
    $array = $log->toArray();
    
    // Request body should be formatted
    expect($array['request_body'])->toContain("\n");
    expect($array['request_body'])->toContain('"name"');
    expect($array['request_body'])->toContain('"John"');
    
    // Response body should be formatted
    expect($array['response_body'])->toContain("\n");
    expect($array['response_body'])->toContain('"id"');
    expect($array['response_body'])->toContain('123');
    
    // Headers should be formatted
    expect($array['request_headers'])->toContain("\n");
    expect($array['request_headers'])->toContain('"Content-Type"');
    expect($array['response_headers'])->toContain("\n");
});

test('ApiLogObject leaves non-JSON strings as-is', function () {
    $log = new ApiLogObject(
        message: 'api_request',
        requestBody: 'plain text, not JSON',
        responseBody: 'also plain text',
    );
    
    $array = $log->toArray();
    
    expect($array['request_body'])->toBe('plain text, not JSON');
    expect($array['response_body'])->toBe('also plain text');
});

test('IntegrationLogObject formats request_body, response_body and headers as pretty JSON', function () {
    $log = new IntegrationLogObject(
        message: 'integration_call',
        integrationName: 'stripe',
        url: 'https://api.stripe.com/v1/charges',
        method: 'POST',
        requestBody: '{"amount":1000,"currency":"usd"}',
        responseBody: '{"id":"ch_123","status":"succeeded"}',
        headers: ['Authorization' => 'Bearer sk_test_123', 'Content-Type' => 'application/json'],
    );
    
    $array = $log->toArray();
    
    // Request body should be formatted
    expect($array['request_body'])->toContain("\n");
    expect($array['request_body'])->toContain('"amount"');
    expect($array['request_body'])->toContain('1000');
    
    // Response body should be formatted
    expect($array['response_body'])->toContain("\n");
    expect($array['response_body'])->toContain('"id"');
    expect($array['response_body'])->toContain('"ch_123"');
    
    // Headers should be formatted
    expect($array['headers'])->toContain("\n");
    expect($array['headers'])->toContain('"Authorization"');
    expect($array['headers'])->toContain('"Bearer sk_test_123"');
});

test('ApiLogObject handles complex nested JSON', function () {
    $complexJson = json_encode([
        'user' => [
            'id' => 123,
            'name' => 'John Doe',
            'address' => [
                'street' => '123 Main St',
                'city' => 'New York',
                'zip' => '10001',
            ],
            'tags' => ['premium', 'active'],
        ],
        'metadata' => [
            'created_at' => '2026-01-22T10:00:00Z',
            'version' => '1.0',
        ],
    ]);
    
    $log = new ApiLogObject(
        message: 'api_request',
        requestBody: $complexJson,
    );
    
    $array = $log->toArray();
    
    // Should be formatted with indentation
    expect($array['request_body'])->toContain("\n");
    expect($array['request_body'])->toContain('"user"');
    expect($array['request_body'])->toContain('"address"');
    expect($array['request_body'])->toContain('"street"');
    
    // Should be valid JSON
    $decoded = json_decode($array['request_body'], true);
    expect($decoded)->toBeArray();
    expect($decoded['user']['id'])->toBe(123);
    expect($decoded['user']['address']['city'])->toBe('New York');
});

test('ApiLogObject handles empty JSON objects and arrays', function () {
    $log = new ApiLogObject(
        message: 'api_request',
        requestBody: '{}',
        responseBody: '[]',
    );
    
    $array = $log->toArray();
    
    // Should be valid JSON (empty objects/arrays may not have newlines when formatted)
    $requestDecoded = json_decode($array['request_body'], true);
    $responseDecoded = json_decode($array['response_body'], true);
    
    expect($requestDecoded)->toBe([]);
    expect($responseDecoded)->toBe([]);
    
    // Verify they are still valid JSON strings
    expect($array['request_body'])->toBeString();
    expect($array['response_body'])->toBeString();
});

test('ApiLogObject handles invalid JSON gracefully', function () {
    $log = new ApiLogObject(
        message: 'api_request',
        requestBody: '{"invalid": json, missing quotes}',
    );
    
    $array = $log->toArray();
    
    // Should return as-is (not formatted, not null)
    expect($array['request_body'])->toBe('{"invalid": json, missing quotes}');
});
