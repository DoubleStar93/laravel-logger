<?php

use Ermetix\LaravelLogger\Facades\LaravelLogger;
use Ermetix\LaravelLogger\Support\Logging\Objects\GeneralLogObject;
use Ermetix\LaravelLogger\Support\Logging\Objects\ApiLogObject;
use Ermetix\LaravelLogger\Support\Logging\Objects\CronLogObject;
use Ermetix\LaravelLogger\Support\Logging\Objects\IntegrationLogObject;
use Ermetix\LaravelLogger\Support\Logging\Objects\OrmLogObject;
use Ermetix\LaravelLogger\Support\Logging\Objects\ErrorLogObject;
use Illuminate\Support\Facades\Log;

test('typed logger general method accepts GeneralLogObject', function () {
    Log::shouldReceive('channel')
        ->once()
        ->andReturnSelf();
    
    Log::shouldReceive('log')
        ->once();
    
    $logObject = new GeneralLogObject(
        message: 'test_message',
        event: 'test_event',
        level: 'info',
    );
    
    LaravelLogger::general($logObject, defer: false);
});

test('typed logger api method accepts ApiLogObject', function () {
    Log::shouldReceive('channel')
        ->once()
        ->andReturnSelf();
    
    Log::shouldReceive('log')
        ->once();
    
    $logObject = new ApiLogObject(
        message: 'api_request',
        method: 'GET',
        path: '/api/test',
        status: 200,
        durationMs: 10,
        level: 'info',
    );
    
    LaravelLogger::api($logObject, defer: false);
});

test('typed logger cron method accepts CronLogObject', function () {
    Log::shouldReceive('channel')
        ->once()
        ->andReturnSelf();
    
    Log::shouldReceive('log')
        ->once();
    
    $logObject = new CronLogObject(
        message: 'job_completed',
        job: 'test:job',
        command: 'php artisan test:job',
        status: 'ok',
        durationMs: 1000,
        level: 'info',
    );
    
    LaravelLogger::cron($logObject, defer: false);
});

test('typed logger integration method accepts IntegrationLogObject', function () {
    Log::shouldReceive('channel')
        ->once()
        ->andReturnSelf();
    
    Log::shouldReceive('log')
        ->once();
    
    $logObject = new IntegrationLogObject(
        message: 'api_call',
        integrationName: 'stripe',
        url: 'https://api.stripe.com/v1/charges',
        method: 'POST',
        status: 200,
        durationMs: 300,
        level: 'info',
    );
    
    LaravelLogger::integration($logObject, defer: false);
});

test('typed logger orm method accepts OrmLogObject', function () {
    Log::shouldReceive('channel')
        ->once()
        ->andReturnSelf();
    
    Log::shouldReceive('log')
        ->once();
    
    $logObject = new OrmLogObject(
        message: 'user_updated',
        model: 'App\Models\User',
        action: 'update',
        query: 'UPDATE users SET email = ? WHERE id = ?',
        durationMs: 12,
        level: 'info',
    );
    
    LaravelLogger::orm($logObject, defer: false);
});

test('typed logger error method accepts ErrorLogObject', function () {
    Log::shouldReceive('channel')
        ->once()
        ->andReturnSelf();
    
    Log::shouldReceive('log')
        ->once();
    
    $logObject = new ErrorLogObject(
        message: 'test_error',
        stackTrace: 'Stack trace',
        exceptionClass: 'Exception',
        file: '/app/test.php',
        line: 10,
        code: 0,
        level: 'error',
    );
    
    LaravelLogger::error($logObject, defer: false);
});

test('typed logger defaults to deferred logging', function () {
    Log::shouldReceive('channel')->never();

    $logObject = new GeneralLogObject(
        message: 'test_message',
        event: 'test_event',
        level: 'info',
    );

    // default defer=true, so nothing is written immediately
    LaravelLogger::general($logObject);
});
