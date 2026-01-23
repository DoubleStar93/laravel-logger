<?php

use Ermetix\LaravelLogger\Http\Middleware\ApiAccessLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

test('ApiAccessLog detectApiVersion extracts version from /api/v1/ path', function () {
    $middleware = new ApiAccessLog();
    
    $reflection = new ReflectionClass($middleware);
    $method = $reflection->getMethod('detectApiVersion');
    $method->setAccessible(true);
    
    $request = Request::create('/api/v1/users', 'GET');
    expect($method->invoke($middleware, $request))->toBe('v1');
    
    $request = Request::create('/api/v2/users', 'GET');
    expect($method->invoke($middleware, $request))->toBe('v2');
    
    $request = Request::create('/api/v2.1/users', 'GET');
    expect($method->invoke($middleware, $request))->toBe('v2.1');
});

test('ApiAccessLog detectApiVersion extracts version from /v1/ path', function () {
    $middleware = new ApiAccessLog();
    
    $reflection = new ReflectionClass($middleware);
    $method = $reflection->getMethod('detectApiVersion');
    $method->setAccessible(true);
    
    $request = Request::create('/v1/users', 'GET');
    expect($method->invoke($middleware, $request))->toBe('v1');
    
    $request = Request::create('/v3.5/posts', 'GET');
    expect($method->invoke($middleware, $request))->toBe('v3.5');
});

test('ApiAccessLog detectApiVersion extracts version from header', function () {
    $middleware = new ApiAccessLog();
    
    $reflection = new ReflectionClass($middleware);
    $method = $reflection->getMethod('detectApiVersion');
    $method->setAccessible(true);
    
    $request = Request::create('/api/test', 'GET');
    $request->headers->set('X-API-Version', 'v2');
    expect($method->invoke($middleware, $request))->toBe('v2');
    
    $request = Request::create('/api/test', 'GET');
    $request->headers->set('API-Version', 'v3');
    expect($method->invoke($middleware, $request))->toBe('v3');
});

test('ApiAccessLog detectApiVersion returns null when no version found', function () {
    $middleware = new ApiAccessLog();
    
    $reflection = new ReflectionClass($middleware);
    $method = $reflection->getMethod('detectApiVersion');
    $method->setAccessible(true);
    
    $request = Request::create('/api/users', 'GET');
    expect($method->invoke($middleware, $request))->toBeNull();
    
    $request = Request::create('/users', 'GET');
    expect($method->invoke($middleware, $request))->toBeNull();
});

test('ApiAccessLog detectAuthenticationMethod detects basic auth', function () {
    $middleware = new ApiAccessLog();
    
    $reflection = new ReflectionClass($middleware);
    $method = $reflection->getMethod('detectAuthenticationMethod');
    $method->setAccessible(true);
    
    Auth::shouldReceive('check')->andReturn(false);
    
    $request = Request::create('/api/test', 'GET');
    $request->headers->set('Authorization', 'Basic dXNlcm5hbWU6cGFzc3dvcmQ=');
    
    expect($method->invoke($middleware, $request))->toBe('basic');
});

test('ApiAccessLog detectAuthenticationMethod detects token auth via currentAccessToken', function () {
    $middleware = new ApiAccessLog();
    
    $reflection = new ReflectionClass($middleware);
    $method = $reflection->getMethod('detectAuthenticationMethod');
    $method->setAccessible(true);
    
    // Create a user class that has currentAccessToken method
    $user = new class {
        public function currentAccessToken() {
            return \Mockery::mock();
        }
    };
    
    Auth::shouldReceive('check')->andReturn(true);
    Auth::shouldReceive('user')->andReturn(null);
    
    $request = \Mockery::mock(Request::class);
    $request->shouldReceive('bearerToken')->andReturn(null);
    $request->shouldReceive('header')->with('X-API-Key')->andReturn(null);
    $request->shouldReceive('header')->with('X-Api-Key')->andReturn(null);
    $request->shouldReceive('hasSession')->andReturn(false);
    $request->shouldReceive('user')->andReturn($user);
    
    // When hasSession is false and user has currentAccessToken method, returns 'token'
    expect($method->invoke($middleware, $request))->toBe('token');
});

test('ApiAccessLog detectAuthenticationMethod detects generic authenticated state', function () {
    $middleware = new ApiAccessLog();
    
    $reflection = new ReflectionClass($middleware);
    $method = $reflection->getMethod('detectAuthenticationMethod');
    $method->setAccessible(true);
    
    $user = \Mockery::mock();
    $user->shouldReceive('currentAccessToken')->andReturn(null);
    
    Auth::shouldReceive('check')->andReturn(true);
    Auth::shouldReceive('user')->andReturn($user);
    
    $request = \Mockery::mock(Request::class);
    $request->shouldReceive('bearerToken')->andReturn(null);
    $request->shouldReceive('header')->with('X-API-Key')->andReturn(null);
    $request->shouldReceive('header')->with('X-Api-Key')->andReturn(null);
    $request->shouldReceive('hasSession')->andReturn(false);
    $request->shouldReceive('user')->andReturn($user);
    
    // When hasSession is false and user has no currentAccessToken, returns 'authenticated'
    expect($method->invoke($middleware, $request))->toBe('authenticated');
});
