<?php

use Ermetix\LaravelLogger\Support\Logging\JsonFileLogWriter;
use Ermetix\LaravelLogger\Support\Logging\Objects\GeneralLogObject;

test('JsonFileLogWriter sanitizeIndex handles special characters', function () {
    $writer = new JsonFileLogWriter();
    
    // Create a log object with a custom index that has special characters
    $log = new GeneralLogObject(message: 'test', level: 'info');
    
    // Use reflection to test sanitizeIndex directly
    $reflection = new ReflectionClass($writer);
    $method = $reflection->getMethod('sanitizeIndex');
    $method->setAccessible(true);
    
    expect($method->invoke($writer, 'api_log'))->toBe('api_log');
    expect($method->invoke($writer, 'API_LOG'))->toBe('api_log'); // Lowercase
    expect($method->invoke($writer, 'api-log'))->toBe('api-log'); // Dash allowed
    expect($method->invoke($writer, 'api_log.test'))->toBe('api_log.test'); // Dot allowed
    expect($method->invoke($writer, 'api/log'))->toBe('api_log'); // Slash replaced
    expect($method->invoke($writer, 'api log'))->toBe('api_log'); // Space replaced
    expect($method->invoke($writer, 'api@log'))->toBe('api_log'); // @ replaced
    expect($method->invoke($writer, ''))->toBe('log'); // Empty string fallback
    expect($method->invoke($writer, '   '))->toBe('log'); // Whitespace only fallback
});

test('JsonFileLogWriter dateFromFilename extracts date from filename', function () {
    $writer = new JsonFileLogWriter();
    
    $reflection = new ReflectionClass($writer);
    $method = $reflection->getMethod('dateFromFilename');
    $method->setAccessible(true);
    
    // Valid date in filename
    $date = $method->invoke($writer, 'api_log-2026-01-22.jsonl');
    expect($date)->toBeInstanceOf(\DateTimeImmutable::class);
    expect($date->format('Y-m-d'))->toBe('2026-01-22');
    
    // Date in middle of filename
    $date = $method->invoke($writer, 'prefix-2026-01-22-suffix.jsonl');
    expect($date)->toBeInstanceOf(\DateTimeImmutable::class);
    expect($date->format('Y-m-d'))->toBe('2026-01-22');
    
    // No date in filename
    $date = $method->invoke($writer, 'api_log.jsonl');
    expect($date)->toBeNull();
    
    // Invalid date format
    $date = $method->invoke($writer, 'api_log-2026-13-45.jsonl'); // Invalid month/day
    expect($date)->toBeNull();
    
    // Edge case: dateFromFilename catch block (when DateTimeImmutable constructor throws)
    // This is hard to test directly, but we can verify the method handles edge cases
    $date = $method->invoke($writer, 'api_log-9999-99-99.jsonl'); // Invalid date that might cause exception
    expect($date)->toBeNull();
});
