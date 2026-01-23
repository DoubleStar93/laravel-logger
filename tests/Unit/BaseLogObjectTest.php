<?php

use Ermetix\LaravelLogger\Support\Logging\Objects\GeneralLogObject;

test('BaseLogObject __call returns existing properties', function () {
    $log = new GeneralLogObject(
        message: 'hello',
        event: 'my_event',
        level: 'info',
    );

    // Provided by BaseLogObject (__call) because property exists.
    expect($log->event())->toBe('my_event');
});

test('BaseLogObject __call throws for unknown properties', function () {
    $log = new GeneralLogObject(message: 'hello');

    expect(fn () => $log->doesNotExist())->toThrow(BadMethodCallException::class);
});

