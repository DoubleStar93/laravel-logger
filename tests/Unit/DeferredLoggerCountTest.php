<?php

use Ermetix\LaravelLogger\Support\Logging\DeferredLogger;

test('DeferredLogger count returns zero when empty', function () {
    $logger = new DeferredLogger();
    
    expect($logger->count())->toBe(0);
});

test('DeferredLogger count returns correct number of accumulated logs', function () {
    $logger = new DeferredLogger();
    
    $logger->defer('opensearch', 'info', 'log1', []);
    expect($logger->count())->toBe(1);
    
    $logger->defer('opensearch', 'info', 'log2', []);
    expect($logger->count())->toBe(2);
    
    $logger->defer('kafka', 'warning', 'log3', []);
    expect($logger->count())->toBe(3);
});

test('DeferredLogger count returns zero after clear', function () {
    $logger = new DeferredLogger();
    
    $logger->defer('opensearch', 'info', 'log1', []);
    $logger->defer('opensearch', 'info', 'log2', []);
    
    expect($logger->count())->toBe(2);
    
    $logger->clear();
    expect($logger->count())->toBe(0);
});

test('DeferredLogger count returns zero after flush', function () {
    $logger = new DeferredLogger();
    
    $logger->defer('opensearch', 'info', 'log1', []);
    $logger->defer('opensearch', 'info', 'log2', []);
    
    expect($logger->count())->toBe(2);
    
    // Mock Log facade to avoid actual logging
    \Illuminate\Support\Facades\Log::shouldReceive('channel')
        ->with('opensearch')
        ->andReturn(new class {
            public function log() {}
            public function getHandlers() { return []; }
        });
    
    $logger->flush();
    expect($logger->count())->toBe(0);
});
