<?php

use Ermetix\LaravelLogger\Jobs\LogToKafka;
use Illuminate\Support\Facades\Log;

test('kafka:test dispatches sync jobs and supports direct channel logging', function () {
    $psr = \Mockery::mock();
    $psr->shouldReceive('info')->once();
    Log::shouldReceive('channel')->with('kafka')->andReturn($psr);

    unset($GLOBALS['__ll_dispatched_jobs'], $GLOBALS['__ll_dispatch_sync_jobs']);

    $this->artisan('kafka:test', ['message' => 'hello', '--count' => 2])
        ->assertSuccessful();

    expect($GLOBALS['__ll_dispatch_sync_jobs'])->toHaveCount(2);
    expect($GLOBALS['__ll_dispatch_sync_jobs'][0])->toBeInstanceOf(LogToKafka::class);
});

test('kafka:test dispatches async jobs when --async is set', function () {
    Log::shouldReceive('channel')->with('kafka')->andThrow(new RuntimeException('direct fail'));

    unset($GLOBALS['__ll_dispatched_jobs'], $GLOBALS['__ll_dispatch_sync_jobs']);

    $this->artisan('kafka:test', ['message' => 'hello', '--count' => 1, '--async' => true])
        ->assertSuccessful();

    expect($GLOBALS['__ll_dispatched_jobs'])->toHaveCount(1);
    expect($GLOBALS['__ll_dispatched_jobs'][0])->toBeInstanceOf(LogToKafka::class);
});

test('kafka:test handles dispatch errors per message', function () {
    $psr = \Mockery::mock();
    $psr->shouldReceive('info')->once();
    Log::shouldReceive('channel')->with('kafka')->andReturn($psr);

    $GLOBALS['__ll_dispatch_sync_throw'] = true;

    $this->artisan('kafka:test', ['message' => 'hello', '--count' => 1])
        ->assertSuccessful();

    unset($GLOBALS['__ll_dispatch_sync_throw']);
});

