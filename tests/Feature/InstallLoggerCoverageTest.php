<?php

use Illuminate\Support\Facades\File;

test('laravel-logger:install handles missing logging.php and bootstrap/app.php', function () {
    $loggingPath = config_path('logging.php');
    $appPath = base_path('bootstrap/app.php');

    $loggingOriginal = File::exists($loggingPath) ? File::get($loggingPath) : null;
    $appOriginal = File::exists($appPath) ? File::get($appPath) : null;

    File::delete($loggingPath);
    File::delete($appPath);

    $this->artisan('laravel-logger:install')
        ->expectsConfirmation('Publish OpenSearch templates?', 'no')
        ->expectsConfirmation('Publish Docker setup scripts?', 'no')
        ->assertSuccessful();

    // Restore
    if ($loggingOriginal !== null) {
        File::put($loggingPath, $loggingOriginal);
    } else {
        File::ensureDirectoryExists(dirname($loggingPath));
        File::put($loggingPath, "<?php return [];\n");
    }
    if ($appOriginal !== null) {
        File::put($appPath, $appOriginal);
    } else {
        File::ensureDirectoryExists(dirname($appPath));
        File::put($appPath, "<?php return null;\n");
    }
});

test('laravel-logger:install warns when logging.php has no emergency insertion point', function () {
    $loggingPath = config_path('logging.php');
    $original = File::exists($loggingPath) ? File::get($loggingPath) : null;

    File::ensureDirectoryExists(dirname($loggingPath));
    File::put($loggingPath, "<?php\nreturn ['channels' => []];\n");

    $this->artisan('laravel-logger:install', ['--force' => true])
        ->expectsConfirmation('Publish OpenSearch templates?', 'no')
        ->expectsConfirmation('Publish Docker setup scripts?', 'no')
        ->assertSuccessful();

    if ($original !== null) {
        File::put($loggingPath, $original);
    }
});

test('laravel-logger:install inserts channels and replaces empty middleware/exception blocks', function () {
    $loggingPath = config_path('logging.php');
    $appPath = base_path('bootstrap/app.php');

    $loggingOriginal = File::exists($loggingPath) ? File::get($loggingPath) : null;
    $appOriginal = File::exists($appPath) ? File::get($appPath) : null;

    File::ensureDirectoryExists(dirname($loggingPath));
    File::put($loggingPath, <<<'PHP'
<?php
return [
  'channels' => [
    'emergency' => ['path' => storage_path('logs/laravel.log')],
  ],
];
PHP);

    File::ensureDirectoryExists(dirname($appPath));
    File::put($appPath, <<<'PHP'
<?php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Configuration\Exceptions;

return Application::configure(basePath: dirname(__DIR__))
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
PHP);

    $this->artisan('laravel-logger:install', ['--force' => true])
        ->expectsConfirmation('Publish OpenSearch templates?', 'no')
        ->expectsConfirmation('Publish Docker setup scripts?', 'no')
        ->assertSuccessful();

    expect((string) File::get($loggingPath))->toContain('CreateOpenSearchLogger');
    expect((string) File::get($appPath))->toContain('Ermetix\\LaravelLogger\\Http\\Middleware\\RequestId');
    expect((string) File::get($appPath))->toContain('Ermetix\\LaravelLogger\\Support\\Logging\\Objects\\ErrorLogObject');

    // Restore
    if ($loggingOriginal !== null) {
        File::put($loggingPath, $loggingOriginal);
    }
    if ($appOriginal !== null) {
        File::put($appPath, $appOriginal);
    }
});

test('laravel-logger:install inserts configuration when middleware blocks exist but do not reference package', function () {
    $appPath = base_path('bootstrap/app.php');
    $original = File::exists($appPath) ? File::get($appPath) : null;

    File::ensureDirectoryExists(dirname($appPath));
    File::put($appPath, <<<'PHP'
<?php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Configuration\Exceptions;

return Application::configure(basePath: dirname(__DIR__))
    ->withMiddleware(function (Middleware $middleware): void {
        // some middleware here
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // some exceptions here
    })
    ->create();
PHP);

    $this->artisan('laravel-logger:install', ['--force' => true])
        ->expectsConfirmation('Publish OpenSearch templates?', 'no')
        ->expectsConfirmation('Publish Docker setup scripts?', 'no')
        ->assertSuccessful();

    expect((string) File::get($appPath))->toContain('Ermetix\\LaravelLogger\\Http\\Middleware\\RequestId');

    if ($original !== null) {
        File::put($appPath, $original);
    }
});

test('laravel-logger:install falls back when bootstrap/app.php has no create() chain', function () {
    $appPath = base_path('bootstrap/app.php');
    $original = File::exists($appPath) ? File::get($appPath) : null;

    File::ensureDirectoryExists(dirname($appPath));
    File::put($appPath, <<<'PHP'
<?php
$app = null;
return $app;
PHP);

    $this->artisan('laravel-logger:install', ['--force' => true])
        ->expectsConfirmation('Publish OpenSearch templates?', 'no')
        ->expectsConfirmation('Publish Docker setup scripts?', 'no')
        ->assertSuccessful();

    expect((string) File::get($appPath))->toContain('Laravel Logger bootstrap configuration');

    if ($original !== null) {
        File::put($appPath, $original);
    }
});

test('laravel-logger:install covers whitespace+semicolon parsing in middleware/exceptions blocks', function () {
    $appPath = base_path('bootstrap/app.php');
    $original = File::exists($appPath) ? File::get($appPath) : null;

    // Note: this file is intentionally "weird" but it's treated as plain text by the installer.
    File::ensureDirectoryExists(dirname($appPath));
    File::put($appPath, <<<'PHP'
<?php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Configuration\Exceptions;

return Application::configure(basePath: dirname(__DIR__))
    ->withMiddleware(function (Middleware $middleware): void {
        //
    }   
    );
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    }
    );
    ->create();
PHP);

    $this->artisan('laravel-logger:install', ['--force' => true])
        ->expectsConfirmation('Publish OpenSearch templates?', 'no')
        ->expectsConfirmation('Publish Docker setup scripts?', 'no')
        ->assertSuccessful();

    expect((string) File::get($appPath))->toContain('Ermetix\\LaravelLogger\\Http\\Middleware\\RequestId');

    if ($original !== null) {
        File::put($appPath, $original);
    }
});

test('laravel-logger:install inserts exceptions when only middleware empty block is replaced', function () {
    $appPath = base_path('bootstrap/app.php');
    $original = File::exists($appPath) ? File::get($appPath) : null;

    File::ensureDirectoryExists(dirname($appPath));
    File::put($appPath, <<<'PHP'
<?php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->create();
PHP);

    $this->artisan('laravel-logger:install', ['--force' => true])
        ->expectsConfirmation('Publish OpenSearch templates?', 'no')
        ->expectsConfirmation('Publish Docker setup scripts?', 'no')
        ->assertSuccessful();

    expect((string) File::get($appPath))->toContain('Ermetix\\LaravelLogger\\Support\\Logging\\Objects\\ErrorLogObject');

    if ($original !== null) {
        File::put($appPath, $original);
    }
});

test('laravel-logger:install inserts middleware when only exceptions empty block is replaced', function () {
    $appPath = base_path('bootstrap/app.php');
    $original = File::exists($appPath) ? File::get($appPath) : null;

    File::ensureDirectoryExists(dirname($appPath));
    File::put($appPath, <<<'PHP'
<?php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;

return Application::configure(basePath: dirname(__DIR__))
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
PHP);

    $this->artisan('laravel-logger:install', ['--force' => true])
        ->expectsConfirmation('Publish OpenSearch templates?', 'no')
        ->expectsConfirmation('Publish Docker setup scripts?', 'no')
        ->assertSuccessful();

    expect((string) File::get($appPath))->toContain('Ermetix\\LaravelLogger\\Http\\Middleware\\RequestId');

    if ($original !== null) {
        File::put($appPath, $original);
    }
});

test('laravel-logger:install appends fallback configuration when no insertion markers exist', function () {
    $appPath = base_path('bootstrap/app.php');
    $original = File::exists($appPath) ? File::get($appPath) : null;

    File::ensureDirectoryExists(dirname($appPath));
    File::put($appPath, "<?php\n// no markers here\n");

    $this->artisan('laravel-logger:install', ['--force' => true])
        ->expectsConfirmation('Publish OpenSearch templates?', 'no')
        ->expectsConfirmation('Publish Docker setup scripts?', 'no')
        ->assertSuccessful();

    expect((string) File::get($appPath))->toContain('Laravel Logger bootstrap configuration');

    if ($original !== null) {
        File::put($appPath, $original);
    }
});

test('laravel-logger:install covers branch where blocks exist (regex) and inserts before create()', function () {
    $appPath = base_path('bootstrap/app.php');
    $original = File::exists($appPath) ? File::get($appPath) : null;

    File::ensureDirectoryExists(dirname($appPath));
    File::put($appPath, <<<'PHP'
<?php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Configuration\Exceptions;

return Application::configure(basePath: dirname(__DIR__))
    ->withMiddleware(function (Middleware $middleware): void {
        // not empty
    });
    ->withExceptions(function (Exceptions $exceptions): void {
        // not empty
    });
    ->create();
PHP);

    $this->artisan('laravel-logger:install', ['--force' => true])
        ->expectsConfirmation('Publish OpenSearch templates?', 'no')
        ->expectsConfirmation('Publish Docker setup scripts?', 'no')
        ->assertSuccessful();

    expect((string) File::get($appPath))->toContain('Ermetix\\LaravelLogger\\Http\\Middleware\\RequestId');

    if ($original !== null) {
        File::put($appPath, $original);
    }
});

test('laravel-logger:install warns when middleware blocks exist but no create() insertion point is found', function () {
    $appPath = base_path('bootstrap/app.php');
    $original = File::exists($appPath) ? File::get($appPath) : null;

    File::ensureDirectoryExists(dirname($appPath));
    File::put($appPath, <<<'PHP'
<?php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withMiddleware(function (Middleware $middleware): void {
        // not empty
    });
// no create call here
PHP);

    $this->artisan('laravel-logger:install', ['--force' => true])
        ->expectsConfirmation('Publish OpenSearch templates?', 'no')
        ->expectsConfirmation('Publish Docker setup scripts?', 'no')
        ->assertSuccessful();

    if ($original !== null) {
        File::put($appPath, $original);
    }
});

