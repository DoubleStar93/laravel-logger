<?php

use Ermetix\LaravelLogger\Listeners\LogModelEvents;
use Ermetix\LaravelLogger\Facades\LaravelLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class TestLogModelEvents extends LogModelEvents
{
    public function getTransactionIdPublic(?string $connectionName): ?string
    {
        return $this->getTransactionId($connectionName);
    }
}

test('LogModelEvents returns early when model events are disabled', function () {
    config(['laravel-logger.orm.model_events_enabled' => false]);

    LaravelLogger::shouldReceive('orm')->never();

    $model = new class extends Model {};
    (new LogModelEvents())->created($model);
    (new LogModelEvents())->updated($model);
    (new LogModelEvents())->deleted($model);
});

test('LogModelEvents getTransactionId returns txn id when in transaction', function () {
    config(['laravel-logger.orm.model_events_enabled' => true]);

    $conn = \Mockery::mock();
    $conn->shouldReceive('transactionLevel')->andReturn(1);
    DB::shouldReceive('getDefaultConnection')->andReturn('sqlite');
    DB::shouldReceive('connection')->with('sqlite')->andReturn($conn);

    $l = new TestLogModelEvents();
    $id = $l->getTransactionIdPublic('sqlite');
    expect($id)->toStartWith('txn-');
});

test('LogModelEvents getTransactionId clears when not in transaction and ignores errors', function () {
    $l = new TestLogModelEvents();

    // Not in transaction -> clears cache branch
    $conn = \Mockery::mock();
    $conn->shouldReceive('transactionLevel')->andReturn(0);
    DB::shouldReceive('getDefaultConnection')->andReturn('sqlite');
    DB::shouldReceive('connection')->with('sqlite')->andReturn($conn);
    expect($l->getTransactionIdPublic('sqlite'))->toBeNull();

    expect(true)->toBeTrue();
});

test('LogModelEvents getTransactionId returns null when DB connection throws', function () {
    $l = new TestLogModelEvents();

    DB::shouldReceive('getDefaultConnection')->andReturn('sqlite');
    DB::shouldReceive('connection')->andThrow(new RuntimeException('boom'));

    expect($l->getTransactionIdPublic('sqlite'))->toBeNull();
});

