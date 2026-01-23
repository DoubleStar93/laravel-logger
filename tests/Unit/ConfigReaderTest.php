<?php

use Ermetix\LaravelLogger\Support\Config\ConfigReader;

beforeEach(function () {
    // Clear config cache before each test
    app()['config']->set('laravel-logger', []);
});

test('ConfigReader get returns config value when present', function () {
    app()['config']->set('laravel-logger.test.key', 'value');
    
    expect(ConfigReader::get('test.key'))->toBe('value');
});

test('ConfigReader get returns default when config is missing', function () {
    expect(ConfigReader::get('test.missing', 'default'))->toBe('default');
});

test('ConfigReader get returns default when config is null', function () {
    app()['config']->set('laravel-logger.test.null', null);
    
    expect(ConfigReader::get('test.null', 'default'))->toBe('default');
});

test('ConfigReader get returns default when config is empty string', function () {
    app()['config']->set('laravel-logger.test.empty', '');
    
    expect(ConfigReader::get('test.empty', 'default'))->toBe('default');
});

test('ConfigReader get returns value when config is 0', function () {
    app()['config']->set('laravel-logger.test.zero', 0);
    
    expect(ConfigReader::get('test.zero', 'default'))->toBe(0);
});

test('ConfigReader get returns value when config is false', function () {
    app()['config']->set('laravel-logger.test.false', false);
    
    expect(ConfigReader::get('test.false', 'default'))->toBe(false);
});

test('ConfigReader getBool returns boolean when config is boolean', function () {
    app()['config']->set('laravel-logger.test.bool', true);
    
    expect(ConfigReader::getBool('test.bool', false))->toBeTrue();
});

test('ConfigReader getBool converts string to boolean', function () {
    app()['config']->set('laravel-logger.test.string_true', 'true');
    app()['config']->set('laravel-logger.test.string_false', 'false');
    
    expect(ConfigReader::getBool('test.string_true', false))->toBeTrue();
    expect(ConfigReader::getBool('test.string_false', true))->toBeFalse();
});

test('ConfigReader getBool converts integer to boolean', function () {
    app()['config']->set('laravel-logger.test.int_one', 1);
    app()['config']->set('laravel-logger.test.int_zero', 0);
    
    expect(ConfigReader::getBool('test.int_one', false))->toBeTrue();
    expect(ConfigReader::getBool('test.int_zero', true))->toBeFalse();
});

test('ConfigReader getBool returns default for invalid types', function () {
    app()['config']->set('laravel-logger.test.array', [1, 2, 3]);
    
    expect(ConfigReader::getBool('test.array', false))->toBeFalse();
});

test('ConfigReader getInt returns integer when config is integer', function () {
    app()['config']->set('laravel-logger.test.int', 42);
    
    expect(ConfigReader::getInt('test.int', 0))->toBe(42);
});

test('ConfigReader getInt converts numeric string to integer', function () {
    app()['config']->set('laravel-logger.test.string', '42');
    
    expect(ConfigReader::getInt('test.string', 0))->toBe(42);
});

test('ConfigReader getInt returns default for non-numeric string', function () {
    app()['config']->set('laravel-logger.test.string', 'not-a-number');
    
    expect(ConfigReader::getInt('test.string', 0))->toBe(0);
});

test('ConfigReader getInt enforces minimum value', function () {
    app()['config']->set('laravel-logger.test.low', 0);
    
    expect(ConfigReader::getInt('test.low', 10, min: 5))->toBe(10);
});

test('ConfigReader getInt enforces maximum value', function () {
    app()['config']->set('laravel-logger.test.high', 100);
    
    expect(ConfigReader::getInt('test.high', 50, max: 60))->toBe(50);
});

test('ConfigReader getInt accepts value within min/max range', function () {
    app()['config']->set('laravel-logger.test.valid', 25);
    
    expect(ConfigReader::getInt('test.valid', 0, min: 10, max: 50))->toBe(25);
});

test('ConfigReader getString returns string when config is string', function () {
    app()['config']->set('laravel-logger.test.string', 'value');
    
    expect(ConfigReader::getString('test.string', 'default'))->toBe('value');
});

test('ConfigReader getString returns default for non-string', function () {
    app()['config']->set('laravel-logger.test.int', 42);
    
    expect(ConfigReader::getString('test.int', 'default'))->toBe('default');
});

test('ConfigReader getString returns default for empty string (get filters it)', function () {
    app()['config']->set('laravel-logger.test.empty', '');
    
    // get() filters empty strings, so getString() receives default
    expect(ConfigReader::getString('test.empty', 'default'))->toBe('default');
});

test('ConfigReader getString rejects empty string when allowEmpty is false', function () {
    app()['config']->set('laravel-logger.test.empty', '');
    
    expect(ConfigReader::getString('test.empty', 'default', allowEmpty: false))->toBe('default');
});

test('ConfigReader getArray returns array when config is array', function () {
    app()['config']->set('laravel-logger.test.array', [1, 2, 3]);
    
    expect(ConfigReader::getArray('test.array', []))->toBe([1, 2, 3]);
});

test('ConfigReader getArray returns default for non-array', function () {
    app()['config']->set('laravel-logger.test.string', 'not-array');
    
    expect(ConfigReader::getArray('test.string', []))->toBe([]);
});

test('ConfigReader getClass returns class name when class exists', function () {
    app()['config']->set('laravel-logger.test.class', \stdClass::class);
    
    expect(ConfigReader::getClass('test.class', \Exception::class))->toBe(\stdClass::class);
});

test('ConfigReader getClass returns default when class does not exist', function () {
    app()['config']->set('laravel-logger.test.class', 'NonExistentClass');
    
    expect(ConfigReader::getClass('test.class', \stdClass::class))->toBe(\stdClass::class);
});

test('ConfigReader getClass returns default when config is empty string', function () {
    app()['config']->set('laravel-logger.test.class', '');
    
    expect(ConfigReader::getClass('test.class', \stdClass::class))->toBe(\stdClass::class);
});

test('ConfigReader getClass returns default when config is null', function () {
    app()['config']->set('laravel-logger.test.class', null);
    
    expect(ConfigReader::getClass('test.class', \stdClass::class))->toBe(\stdClass::class);
});

test('ConfigReader getUrl returns URL when config is valid URL', function () {
    app()['config']->set('laravel-logger.test.url', 'http://example.com');
    
    expect(ConfigReader::getUrl('test.url', 'http://default.com'))->toBe('http://example.com');
});

test('ConfigReader getUrl returns default when config is invalid URL', function () {
    app()['config']->set('laravel-logger.test.url', 'not-a-url');
    
    expect(ConfigReader::getUrl('test.url', 'http://default.com'))->toBe('http://default.com');
});

test('ConfigReader getUrl returns default when config is null', function () {
    app()['config']->set('laravel-logger.test.url', null);
    
    expect(ConfigReader::getUrl('test.url', 'http://default.com'))->toBe('http://default.com');
});

test('ConfigReader getUrl validates https URLs', function () {
    app()['config']->set('laravel-logger.test.url', 'https://secure.example.com');
    
    expect(ConfigReader::getUrl('test.url', 'http://default.com'))->toBe('https://secure.example.com');
});

test('ConfigReader getString with allowEmpty false returns default when get returns null', function () {
    // When config is missing, get() returns default (null in this case)
    // getString() then receives null and returns default
    expect(ConfigReader::getString('test.missing', 'default', allowEmpty: false))->toBe('default');
});

test('ConfigReader getUrl handles null from getString', function () {
    // When config is missing, getString() returns null (default)
    // getUrl() should handle null and return default
    expect(ConfigReader::getUrl('test.missing', 'http://default.com'))->toBe('http://default.com');
});
