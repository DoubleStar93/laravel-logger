<?php

use Illuminate\Support\Facades\File;

test('install command publishes configuration', function () {
    $configPath = config_path('laravel-logger.php');
    
    // Clean up if exists
    if (File::exists($configPath)) {
        File::delete($configPath);
    }
    
    $this->artisan('laravel-logger:install', ['--force' => true])
        ->expectsConfirmation('Publish OpenSearch templates?', 'no')
        ->expectsConfirmation('Publish Docker setup scripts?', 'no')
        ->assertSuccessful();
    
    expect(File::exists($configPath))->toBeTrue();
});

test('install command adds logging channels to config/logging.php', function () {
    $loggingPath = config_path('logging.php');

    $originalContent = File::exists($loggingPath) ? File::get($loggingPath) : null;

    // Always write a known-good skeleton with an 'emergency' channel (needed for insertion).
    File::ensureDirectoryExists(dirname($loggingPath));
    File::put($loggingPath, <<<'PHP'
<?php

return [
    'default' => env('LOG_CHANNEL', 'stack'),

    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['single'],
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],
    ],
];
PHP);
    
    // Remove existing channels if present
    $content = preg_replace(
        "/\s*\/\/ Kafka.*?'opensearch' => \[.*?\],\s*/s",
        '',
        $originalContent
    );
    File::put($loggingPath, $content);
    
    $this->artisan('laravel-logger:install', ['--force' => true])
        ->expectsConfirmation('Publish OpenSearch templates?', 'no')
        ->expectsConfirmation('Publish Docker setup scripts?', 'no')
        ->assertSuccessful();
    
    $newContent = File::get($loggingPath);
    
    expect($newContent)->toContain('Ermetix\\LaravelLogger\\Logging\\CreateOpenSearchLogger');
    expect($newContent)->toContain('Ermetix\\LaravelLogger\\Logging\\CreateKafkaLogger');
    
    // Restore original
    if ($originalContent !== null) {
        File::put($loggingPath, $originalContent);
    } else {
        File::delete($loggingPath);
    }
});

test('install command adds middleware to bootstrap/app.php', function () {
    $appPath = base_path('bootstrap/app.php');

    // Testbench may not ship a default bootstrap/app.php; create a minimal one if missing.
    if (!File::exists($appPath)) {
        File::ensureDirectoryExists(dirname($appPath));
        File::put($appPath, <<<'PHP'
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
PHP);
    }

    $originalContent = File::get($appPath);
    
    // Remove existing middleware if present
    $content = preg_replace(
        "/\s*->withMiddleware\(function.*?\);\s*/s",
        '',
        $originalContent
    );
    $content = preg_replace(
        "/\s*->withExceptions\(function.*?\);\s*/s",
        '',
        $content
    );
    File::put($appPath, $content);
    
    $this->artisan('laravel-logger:install', ['--force' => true])
        ->expectsConfirmation('Publish OpenSearch templates?', 'no')
        ->expectsConfirmation('Publish Docker setup scripts?', 'no')
        ->assertSuccessful();
    
    $newContent = File::get($appPath);
    
    expect($newContent)->toContain('Ermetix\\LaravelLogger\\Http\\Middleware\\RequestId');
    expect($newContent)->toContain('Ermetix\\LaravelLogger\\Http\\Middleware\\ApiAccessLog');
    expect($newContent)->toContain('Ermetix\\LaravelLogger\\Http\\Middleware\\FlushDeferredLogs');
    
    // Restore original
    File::put($appPath, $originalContent);
});

test('install command does not overwrite existing config without force', function () {
    $loggingPath = config_path('logging.php');

    $originalContent = File::exists($loggingPath) ? File::get($loggingPath) : null;

    // Start from a known-good skeleton.
    File::ensureDirectoryExists(dirname($loggingPath));
    File::put($loggingPath, <<<'PHP'
<?php
return [
  'default' => 'stack',
  'channels' => [
    'stack' => ['driver' => 'stack', 'channels' => ['single']],
    'single' => ['driver' => 'single', 'path' => storage_path('logs/laravel.log')],
    'emergency' => ['path' => storage_path('logs/laravel.log')],
  ],
];
PHP);
    
    // First install with force to add channels.
    $this->artisan('laravel-logger:install', ['--force' => true])
        ->expectsConfirmation('Publish OpenSearch templates?', 'no')
        ->expectsConfirmation('Publish Docker setup scripts?', 'no')
        ->assertSuccessful();
    
    $contentBefore = File::get($loggingPath);
    
    // Try to install again without force
    $this->artisan('laravel-logger:install')
        ->expectsConfirmation('Publish OpenSearch templates?', 'no')
        ->expectsConfirmation('Publish Docker setup scripts?', 'no')
        ->expectsOutput('⚠️  Logging channels already configured. Use --force to overwrite.')
        ->assertSuccessful();
    
    $contentAfter = File::get($loggingPath);
    
    expect($contentAfter)->toBe($contentBefore);

    // Restore original
    if ($originalContent !== null) {
        File::put($loggingPath, $originalContent);
    } else {
        File::delete($loggingPath);
    }
});

test('install command can publish opensearch templates', function () {
    $opensearchPath = base_path('opensearch');
    
    // Clean up if exists
    if (File::exists($opensearchPath)) {
        File::deleteDirectory($opensearchPath);
    }
    
    $this->artisan('laravel-logger:install', ['--force' => true])
        ->expectsConfirmation('Publish OpenSearch templates?', 'yes')
        ->expectsConfirmation('Publish Docker setup scripts?', 'no')
        ->assertSuccessful();
    
    expect(File::exists($opensearchPath))->toBeTrue();
    expect(File::exists($opensearchPath.'/index-templates'))->toBeTrue();
});

test('install command can publish docker setup', function () {
    $dockerPath = base_path('docker/opensearch');
    
    // Clean up if exists
    if (File::exists($dockerPath)) {
        File::deleteDirectory($dockerPath);
    }
    
    $this->artisan('laravel-logger:install', ['--force' => true])
        ->expectsConfirmation('Publish OpenSearch templates?', 'no')
        ->expectsConfirmation('Publish Docker setup scripts?', 'yes')
        ->assertSuccessful();
    
    expect(File::exists($dockerPath))->toBeTrue();
    expect(File::exists($dockerPath.'/docker-compose.example.yml'))->toBeTrue();
    expect(File::exists($dockerPath.'/setup.php'))->toBeTrue();
});
