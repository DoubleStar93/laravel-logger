<?php

use Ermetix\LaravelLogger\Facades\LaravelLogger;
use Illuminate\Support\Facades\Log;

test('opensearch:test runs without sleeping and handles errors per log type', function () {
    $GLOBALS['__ll_disable_sleep'] = true;

    $psr = \Mockery::mock();
    $psr->shouldReceive('info')->once();
    Log::shouldReceive('channel')->with('opensearch')->andReturn($psr);

    // Force each typed log call to throw so every catch block is executed.
    LaravelLogger::shouldReceive('api')->once()->andThrow(new RuntimeException('api'));
    LaravelLogger::shouldReceive('general')->once()->andThrow(new RuntimeException('general'));
    LaravelLogger::shouldReceive('job')->once()->andThrow(new RuntimeException('job'));
    LaravelLogger::shouldReceive('integration')->once()->andThrow(new RuntimeException('integration'));
    LaravelLogger::shouldReceive('orm')->once()->andThrow(new RuntimeException('orm'));
    LaravelLogger::shouldReceive('error')->once()->andThrow(new RuntimeException('error'));

    $this->artisan('opensearch:test')->assertSuccessful();

    unset($GLOBALS['__ll_disable_sleep']);
});

test('opensearch:test covers success paths for all log types', function () {
    $GLOBALS['__ll_disable_sleep'] = true;

    $psr = \Mockery::mock();
    $psr->shouldReceive('info')->once();
    Log::shouldReceive('channel')->with('opensearch')->andReturn($psr);

    LaravelLogger::shouldReceive('api')->once();
    LaravelLogger::shouldReceive('general')->once();
    LaravelLogger::shouldReceive('job')->once();
    LaravelLogger::shouldReceive('integration')->once();
    LaravelLogger::shouldReceive('orm')->once();
    LaravelLogger::shouldReceive('error')->once();

    $this->artisan('opensearch:test')->assertSuccessful();

    unset($GLOBALS['__ll_disable_sleep']);
});

