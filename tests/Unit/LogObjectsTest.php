<?php

use Ermetix\LaravelLogger\Support\Logging\Objects\ApiLogObject;
use Ermetix\LaravelLogger\Support\Logging\Objects\GeneralLogObject;
use Ermetix\LaravelLogger\Support\Logging\Objects\CronLogObject;
use Ermetix\LaravelLogger\Support\Logging\Objects\IntegrationLogObject;
use Ermetix\LaravelLogger\Support\Logging\Objects\OrmLogObject;
use Ermetix\LaravelLogger\Support\Logging\Objects\ErrorLogObject;

test('general log object has correct index and level', function () {
    $log = new GeneralLogObject(
        message: 'test_message',
        event: 'test_event',
        level: 'info',
    );
    
    expect($log->index())->toBe('general_log');
    expect($log->level())->toBe('info');
    expect($log->message())->toBe('test_message');
});

test('api log object contains all required fields', function () {
    $log = new ApiLogObject(
        message: 'api_request',
        method: 'POST',
        path: '/api/users',
        status: 201,
        durationMs: 45,
        level: 'info',
    );
    
    expect($log->index())->toBe('api_log');
    expect($log->method())->toBe('POST');
    expect($log->path())->toBe('/api/users');
    expect($log->status())->toBe(201);
    expect($log->durationMs())->toBe(45);
});

test('cron log object contains job information', function () {
    $log = new CronLogObject(
        message: 'job_completed',
        job: 'test:job',
        command: 'php artisan test:job',
        status: 'ok',
        durationMs: 1000,
        level: 'info',
    );
    
    expect($log->index())->toBe('cron_log');
    expect($log->job())->toBe('test:job');
    expect($log->command())->toBe('php artisan test:job');
    expect($log->status())->toBe('ok');
});

test('integration log object contains integration details', function () {
    $log = new IntegrationLogObject(
        message: 'api_call',
        integrationName: 'stripe',
        url: 'https://api.stripe.com/v1/charges',
        method: 'POST',
        status: 200,
        durationMs: 300,
        level: 'info',
    );
    
    expect($log->index())->toBe('integration_log');
    expect($log->integrationName())->toBe('stripe');
    expect($log->url())->toBe('https://api.stripe.com/v1/charges');
});

test('orm log object contains database operation details', function () {
    $log = new OrmLogObject(
        message: 'user_updated',
        model: 'App\Models\User',
        action: 'update',
        query: 'UPDATE users SET email = ? WHERE id = ?',
        durationMs: 12,
        level: 'info',
    );
    
    expect($log->index())->toBe('orm_log');
    expect($log->model())->toBe('App\Models\User');
    expect($log->action())->toBe('update');
    expect($log->query())->toBe('UPDATE users SET email = ? WHERE id = ?');
});

test('error log object contains error details', function () {
    $log = new ErrorLogObject(
        message: 'test_error',
        stackTrace: 'Stack trace here',
        exceptionClass: 'Exception',
        file: '/app/test.php',
        line: 10,
        code: 0,
        level: 'error',
    );
    
    expect($log->index())->toBe('error_log');
    expect($log->stackTrace())->toBe('Stack trace here');
    expect($log->exceptionClass())->toBe('Exception');
    expect($log->file())->toBe('/app/test.php');
    expect($log->line())->toBe(10);
});

test('log objects return array representation', function () {
    $log = new GeneralLogObject(
        message: 'test_message',
        event: 'test_event',
        userId: '123',
        level: 'info',
    );
    
    $array = $log->toArray();
    
    expect($array)->toBeArray();
    expect($array['message'])->toBe('test_message');
    expect($array['event'])->toBe('test_event');
    expect($array['user_id'])->toBe('123');
});
