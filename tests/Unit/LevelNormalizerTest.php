<?php

use Ermetix\LaravelLogger\Support\Logging\LevelNormalizer;
use Monolog\Level;

test('LevelNormalizer normalizes string level to Level enum', function () {
    expect(LevelNormalizer::normalize('debug'))->toBe(Level::Debug);
    expect(LevelNormalizer::normalize('info'))->toBe(Level::Info);
    expect(LevelNormalizer::normalize('notice'))->toBe(Level::Notice);
    expect(LevelNormalizer::normalize('warning'))->toBe(Level::Warning);
    expect(LevelNormalizer::normalize('error'))->toBe(Level::Error);
    expect(LevelNormalizer::normalize('critical'))->toBe(Level::Critical);
    expect(LevelNormalizer::normalize('alert'))->toBe(Level::Alert);
    expect(LevelNormalizer::normalize('emergency'))->toBe(Level::Emergency);
});

test('LevelNormalizer returns Level enum as-is when already Level', function () {
    $level = Level::Info;
    expect(LevelNormalizer::normalize($level))->toBe($level);
});

test('LevelNormalizer handles uppercase string levels', function () {
    expect(LevelNormalizer::normalize('DEBUG'))->toBe(Level::Debug);
    expect(LevelNormalizer::normalize('INFO'))->toBe(Level::Info);
    expect(LevelNormalizer::normalize('WARNING'))->toBe(Level::Warning);
});
