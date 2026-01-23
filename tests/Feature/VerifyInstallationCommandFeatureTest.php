<?php

use Illuminate\Support\Facades\File;

test('laravel-logger:verify succeeds when installation looks correct', function () {
    File::ensureDirectoryExists(base_path('packages/laravel-logger'));
    File::put(config_path('laravel-logger.php'), "<?php return [];\n");

    $loggingPath = config_path('logging.php');
    File::ensureDirectoryExists(dirname($loggingPath));
    File::put($loggingPath, <<<'PHP'
<?php
// Ermetix\LaravelLogger\Logging\CreateKafkaLogger
// Ermetix\LaravelLogger\Logging\CreateOpenSearchLogger
return [];
PHP);

    $appPath = base_path('bootstrap/app.php');
    File::ensureDirectoryExists(dirname($appPath));
    File::put($appPath, <<<'PHP'
<?php
// Ermetix\LaravelLogger\Http\Middleware\RequestId
// Ermetix\LaravelLogger\Http\Middleware\ApiAccessLog
// Ermetix\LaravelLogger\Http\Middleware\FlushDeferredLogs
// Ermetix\LaravelLogger\Support\Logging\Objects\ErrorLogObject
return null;
PHP);

    $this->artisan('laravel-logger:verify')
        ->assertExitCode(0);
});

test('laravel-logger:verify fails when installation is missing', function () {
    File::deleteDirectory(base_path('packages/laravel-logger'));
    File::delete(config_path('laravel-logger.php'));
    File::delete(config_path('logging.php'));
    File::delete(base_path('bootstrap/app.php'));

    $this->artisan('laravel-logger:verify')
        ->assertExitCode(1);

    // Restore minimal files for other tests that may rely on them.
    File::ensureDirectoryExists(dirname(config_path('logging.php')));
    File::put(config_path('logging.php'), "<?php return [];\n");
    File::ensureDirectoryExists(dirname(base_path('bootstrap/app.php')));
    File::put(base_path('bootstrap/app.php'), "<?php return null;\n");
});

