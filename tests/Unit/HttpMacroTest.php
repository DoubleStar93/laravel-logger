<?php

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Http;

test('Http macro withTraceContext adds trace_id and parent_request_id headers', function () {
    Context::flush();
    Context::add('trace_id', 'trace-123');
    Context::add('request_id', 'request-456');

    // Mock HTTP facade to capture headers
    Http::fake([
        '*' => Http::response(['ok' => true], 200),
    ]);

    Http::withTraceContext()->get('https://api.example.com/test');

    Http::assertSent(function ($request) {
        $headers = $request->headers();
        
        // trace_id should be propagated
        // request_id should be sent as X-Parent-Request-Id (not X-Request-Id)
        return isset($headers['X-Trace-Id'][0]) 
            && $headers['X-Trace-Id'][0] === 'trace-123'
            && isset($headers['X-Parent-Request-Id'][0])
            && $headers['X-Parent-Request-Id'][0] === 'request-456'
            && (!isset($headers['X-Request-Id']) || empty($headers['X-Request-Id']));
    });
});

test('Http macro withTraceContext works when only trace_id is present', function () {
    Context::flush();
    Context::add('trace_id', 'trace-789');
    // No request_id

    Http::fake([
        '*' => Http::response(['ok' => true], 200),
    ]);

    Http::withTraceContext()->get('https://api.example.com/test');

    Http::assertSent(function ($request) {
        $headers = $request->headers();
        
        return isset($headers['X-Trace-Id'][0]) 
            && $headers['X-Trace-Id'][0] === 'trace-789'
            && (!isset($headers['X-Parent-Request-Id']) || empty($headers['X-Parent-Request-Id']))
            && (!isset($headers['X-Request-Id']) || empty($headers['X-Request-Id']));
    });
});

test('Http macro withTraceContext works when context is empty', function () {
    Context::flush();
    // No trace_id or request_id

    Http::fake([
        '*' => Http::response(['ok' => true], 200),
    ]);

    Http::withTraceContext()->get('https://api.example.com/test');

    Http::assertSent(function ($request) {
        $headers = $request->headers();
        
        // Should not add headers if context is empty
        return (!isset($headers['X-Trace-Id']) || empty($headers['X-Trace-Id']))
            && (!isset($headers['X-Request-Id']) || empty($headers['X-Request-Id']));
    });
});

test('Http macro withTraceContext can be chained with other methods', function () {
    Context::flush();
    Context::add('trace_id', 'trace-chain');
    Context::add('request_id', 'request-chain');

    Http::fake([
        '*' => Http::response(['ok' => true], 200),
    ]);

    Http::withTraceContext()
        ->withHeaders(['Authorization' => 'Bearer token'])
        ->post('https://api.example.com/test', ['data' => 'value']);

    Http::assertSent(function ($request) {
        $headers = $request->headers();
        
        return isset($headers['X-Trace-Id'][0]) 
            && $headers['X-Trace-Id'][0] === 'trace-chain'
            && isset($headers['X-Parent-Request-Id'][0])
            && $headers['X-Parent-Request-Id'][0] === 'request-chain'
            && isset($headers['Authorization'][0])
            && $headers['Authorization'][0] === 'Bearer token';
    });
});

test('Http calls without withTraceContext do not add trace headers', function () {
    Context::flush();
    Context::add('trace_id', 'trace-123');
    Context::add('request_id', 'request-456');

    Http::fake([
        '*' => Http::response(['ok' => true], 200),
    ]);

    // Normal HTTP call without withTraceContext
    Http::withHeaders(['Authorization' => 'Bearer token'])
        ->get('https://api.example.com/test');

    Http::assertSent(function ($request) {
        $headers = $request->headers();
        
        // Should have Authorization header
        expect($headers['Authorization'][0] ?? null)->toBe('Bearer token');
        
        // Should NOT have trace headers when not using withTraceContext
        return (!isset($headers['X-Trace-Id']) || empty($headers['X-Trace-Id']))
            && (!isset($headers['X-Parent-Request-Id']) || empty($headers['X-Parent-Request-Id']));
    });
});

test('Http macro withTraceContext works when only request_id is present', function () {
    Context::flush();
    Context::add('request_id', 'request-only-123');
    // No trace_id

    Http::fake([
        '*' => Http::response(['ok' => true], 200),
    ]);

    Http::withTraceContext()->get('https://api.example.com/test');

    Http::assertSent(function ($request) {
        $headers = $request->headers();
        
        // Should have parent_request_id but no trace_id
        return (!isset($headers['X-Trace-Id']) || empty($headers['X-Trace-Id']))
            && isset($headers['X-Parent-Request-Id'][0])
            && $headers['X-Parent-Request-Id'][0] === 'request-only-123';
    });
});

test('Http macro withTraceContext ignores empty string values', function () {
    Context::flush();
    Context::add('trace_id', ''); // Empty string
    Context::add('request_id', ''); // Empty string

    Http::fake([
        '*' => Http::response(['ok' => true], 200),
    ]);

    Http::withTraceContext()->get('https://api.example.com/test');

    Http::assertSent(function ($request) {
        $headers = $request->headers();
        
        // Should not add headers for empty strings
        return (!isset($headers['X-Trace-Id']) || empty($headers['X-Trace-Id']))
            && (!isset($headers['X-Parent-Request-Id']) || empty($headers['X-Parent-Request-Id']));
    });
});

test('Http macro withTraceContext works with different HTTP methods', function () {
    Context::flush();
    Context::add('trace_id', 'trace-methods');
    Context::add('request_id', 'request-methods');

    Http::fake([
        '*' => Http::response(['ok' => true], 200),
    ]);

    // Test PUT
    Http::withTraceContext()->put('https://api.example.com/test', ['data' => 'value']);
    Http::assertSent(function ($request) {
        return $request->method() === 'PUT'
            && isset($request->headers()['X-Trace-Id'][0])
            && $request->headers()['X-Trace-Id'][0] === 'trace-methods';
    });

    // Test DELETE
    Http::withTraceContext()->delete('https://api.example.com/test');
    Http::assertSent(function ($request) {
        return $request->method() === 'DELETE'
            && isset($request->headers()['X-Trace-Id'][0])
            && $request->headers()['X-Trace-Id'][0] === 'trace-methods';
    });

    // Test PATCH
    Http::withTraceContext()->patch('https://api.example.com/test', ['data' => 'value']);
    Http::assertSent(function ($request) {
        return $request->method() === 'PATCH'
            && isset($request->headers()['X-Trace-Id'][0])
            && $request->headers()['X-Trace-Id'][0] === 'trace-methods';
    });
});
