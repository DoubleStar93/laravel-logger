<?php

use Ermetix\LaravelLogger\Jobs\LogToKafka;
use Illuminate\Support\Facades\Log;

test('LogToKafka job writes to kafka channel with default metadata', function () {
    Log::shouldReceive('channel')
        ->once()
        ->with('kafka')
        ->andReturnSelf();

    Log::shouldReceive('info')
        ->once()
        ->with('kafka_test', \Mockery::on(function (array $context) {
            expect($context)->toHaveKey('log_index', 'general_log');
            expect($context)->toHaveKey('event_id');
            expect($context)->toHaveKey('sent_at');
            expect($context)->toHaveKey('app_env');
            return true;
        }));

    (new LogToKafka())->handle();
});

