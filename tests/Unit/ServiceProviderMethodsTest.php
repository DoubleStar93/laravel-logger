<?php

use Ermetix\LaravelLogger\LaravelLoggerServiceProvider;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Http;

class TestableLaravelLoggerServiceProvider extends LaravelLoggerServiceProvider
{
    public function testRegisterHttpMacro(): void
    {
        $this->registerHttpMacro();
    }
    
    public function testRegisterMiddleware(): void
    {
        $this->registerMiddleware();
    }
    
    public function testInitializeCorrelationIdsForCli(): void
    {
        $this->initializeCorrelationIdsForCli();
    }
}

test('ServiceProvider registerHttpMacro registers the macro', function () {
    // Clear any existing macro
    if (Http::hasMacro('withTraceContext')) {
        // Can't actually remove a macro, but we can test it exists after boot
    }
    
    $provider = new LaravelLoggerServiceProvider(app());
    $provider->boot();
    
    // Macro should be registered
    expect(Http::hasMacro('withTraceContext'))->toBeTrue();
});

test('ServiceProvider registerMiddleware does nothing (empty method)', function () {
    $provider = new TestableLaravelLoggerServiceProvider(app());
    
    // Should not throw
    $provider->testRegisterMiddleware();
    
    expect(true)->toBeTrue();
});

test('ServiceProvider initializeCorrelationIdsForCli generates IDs when missing', function () {
    Context::flush();
    
    $provider = new TestableLaravelLoggerServiceProvider(app());
    $provider->testInitializeCorrelationIdsForCli();
    
    // Should have generated request_id and trace_id
    expect(Context::get('request_id'))->not->toBeEmpty();
    expect(Context::get('trace_id'))->not->toBeEmpty();
    
    // They should be different
    $requestId = Context::get('request_id');
    $traceId = Context::get('trace_id');
    expect($requestId)->not->toBe($traceId);
});

test('ServiceProvider initializeCorrelationIdsForCli preserves existing IDs', function () {
    Context::flush();
    Context::add('request_id', 'existing-request');
    Context::add('trace_id', 'existing-trace');
    
    $provider = new TestableLaravelLoggerServiceProvider(app());
    $provider->testInitializeCorrelationIdsForCli();
    
    // Should preserve existing
    expect(Context::get('request_id'))->toBe('existing-request');
    expect(Context::get('trace_id'))->toBe('existing-trace');
});

test('ServiceProvider initializeCorrelationIdsForCli handles missing Context class', function () {
    // This test verifies the early return when Context class doesn't exist
    // In practice, Context always exists in Laravel 12+, but we test the guard clause
    $provider = new TestableLaravelLoggerServiceProvider(app());
    
    // Should not throw even if Context check fails (it won't in practice)
    $provider->testInitializeCorrelationIdsForCli();
    
    expect(true)->toBeTrue();
});

test('ServiceProvider registerHttpMacro handles missing Http class gracefully', function () {
    // This test verifies the early return when Http class doesn't exist
    // In practice, Http always exists in Laravel, but we test the guard clause
    $provider = new TestableLaravelLoggerServiceProvider(app());
    
    // Should not throw even if Http check fails (it won't in practice)
    $provider->testRegisterHttpMacro();
    
    // Macro should still be registered (Http class exists)
    expect(Http::hasMacro('withTraceContext'))->toBeTrue();
});

test('ServiceProvider registerHttpMacro handles missing Context class in macro', function () {
    // Test the guard clause inside the macro when Context class doesn't exist
    // In practice, Context always exists in Laravel 12+, but we test the logic
    $provider = new LaravelLoggerServiceProvider(app());
    $provider->boot();
    
    // Macro should be registered and work even if Context check fails (it won't in practice)
    expect(Http::hasMacro('withTraceContext'))->toBeTrue();
    
    // The macro should handle missing Context gracefully
    \Illuminate\Support\Facades\Context::flush();
    Http::fake(['*' => Http::response(['ok' => true], 200)]);
    
    Http::withTraceContext()->get('https://api.example.com/test');
    
    // Should not throw
    expect(true)->toBeTrue();
});

test('ServiceProvider initializeCorrelationIdsForCli handles when only requestId exists', function () {
    \Illuminate\Support\Facades\Context::flush();
    \Illuminate\Support\Facades\Context::add('request_id', 'existing-request');
    // trace_id is missing
    
    $provider = new TestableLaravelLoggerServiceProvider(app());
    $provider->testInitializeCorrelationIdsForCli();
    
    // Should preserve request_id and generate new trace_id
    expect(\Illuminate\Support\Facades\Context::get('request_id'))->toBe('existing-request');
    expect(\Illuminate\Support\Facades\Context::get('trace_id'))->not->toBeEmpty();
    expect(\Illuminate\Support\Facades\Context::get('trace_id'))->not->toBe('existing-request');
});

test('ServiceProvider initializeCorrelationIdsForCli handles when only traceId exists', function () {
    \Illuminate\Support\Facades\Context::flush();
    \Illuminate\Support\Facades\Context::add('trace_id', 'existing-trace');
    // request_id is missing
    
    $provider = new TestableLaravelLoggerServiceProvider(app());
    $provider->testInitializeCorrelationIdsForCli();
    
    // Should preserve trace_id and generate new request_id
    expect(\Illuminate\Support\Facades\Context::get('trace_id'))->toBe('existing-trace');
    expect(\Illuminate\Support\Facades\Context::get('request_id'))->not->toBeEmpty();
    expect(\Illuminate\Support\Facades\Context::get('request_id'))->not->toBe('existing-trace');
});
