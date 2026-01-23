<?php

use Ermetix\LaravelLogger\Console\Commands\VerifyInstallation;
use Ermetix\LaravelLogger\Support\Logging\DeferredLogger;
use Ermetix\LaravelLogger\Support\Logging\MultiChannelLogger;
use Ermetix\LaravelLogger\Support\Logging\TypedLogger;
use Illuminate\Support\Facades\File;

class TestVerifyInstallationCommand extends VerifyInstallation
{
    public function __construct()
    {
        parent::__construct();

        // Ensure the command has an output instance when called directly in unit tests.
        $this->setOutput(new \Illuminate\Console\OutputStyle(
            new \Symfony\Component\Console\Input\ArrayInput([]),
            new \Symfony\Component\Console\Output\BufferedOutput(),
        ));
    }

    public function checkPackageInstalledPublic(): bool { return $this->checkPackageInstalled(); }
    public function checkServiceProviderPublic(): bool { return $this->checkServiceProvider(); }
    public function checkConfigFilePublic(): bool { return $this->checkConfigFile(); }
    public function checkLoggingChannelsPublic(): bool { return $this->checkLoggingChannels(); }
    public function checkMiddlewarePublic(): bool { return $this->checkMiddleware(); }
    public function checkExceptionHandlingPublic(): bool { return $this->checkExceptionHandling(); }
    public function checkArtisanCommandsPublic(): bool { return $this->checkArtisanCommands(); }
    public function checkContainerBindingsPublic(): bool { return $this->checkContainerBindings(); }
    public function displayResultsPublic(array $checks): void { $this->displayResults($checks); }
}

test('VerifyInstallation checkServiceProvider handles missing class (simulated)', function () {
    $cmd = new TestVerifyInstallationCommand();

    $GLOBALS['__ll_class_exists'] = [
        'Ermetix\\LaravelLogger\\LaravelLoggerServiceProvider' => false,
    ];

    expect($cmd->checkServiceProviderPublic())->toBeFalse();

    unset($GLOBALS['__ll_class_exists']);
});

test('VerifyInstallation checks pass when expected files/strings exist', function () {
    $cmd = new TestVerifyInstallationCommand();

    // Package installed
    File::ensureDirectoryExists(base_path('packages/laravel-logger'));

    // Config file exists
    File::put(config_path('laravel-logger.php'), "<?php return [];\n");

    // Ensure logging.php exists for this testbench environment.
    $loggingPath = config_path('logging.php');
    if (!File::exists($loggingPath)) {
        File::ensureDirectoryExists(dirname($loggingPath));
        File::put($loggingPath, "<?php return [];\n");
    }

    // logging.php contains required channels
    $loggingContent = (string) File::get($loggingPath);
    if (!str_contains($loggingContent, 'Ermetix\\LaravelLogger\\Logging\\CreateKafkaLogger')) {
        $loggingContent .= "\n// Ermetix\\LaravelLogger\\Logging\\CreateKafkaLogger\n";
    }
    if (!str_contains($loggingContent, 'Ermetix\\LaravelLogger\\Logging\\CreateOpenSearchLogger')) {
        $loggingContent .= "\n// Ermetix\\LaravelLogger\\Logging\\CreateOpenSearchLogger\n";
    }
    File::put($loggingPath, $loggingContent);

    // bootstrap/app.php contains required middleware + exception strings
    $appPath = base_path('bootstrap/app.php');
    if (!File::exists($appPath)) {
        File::ensureDirectoryExists(dirname($appPath));
        File::put($appPath, "<?php\n// test bootstrap\n");
    }
    $appContent = (string) File::get($appPath);
    foreach ([
        'Ermetix\\LaravelLogger\\Http\\Middleware\\RequestId',
        'Ermetix\\LaravelLogger\\Http\\Middleware\\ApiAccessLog',
        'Ermetix\\LaravelLogger\\Http\\Middleware\\FlushDeferredLogs',
        'Ermetix\\LaravelLogger\\Support\\Logging\\Objects\\ErrorLogObject',
    ] as $needle) {
        if (!str_contains($appContent, $needle)) {
            $appContent .= "\n// {$needle}\n";
        }
    }
    File::put($appPath, $appContent);

    // Container bindings (should already exist via ServiceProvider)
    expect(app()->bound(DeferredLogger::class))->toBeTrue();
    expect(app()->bound(TypedLogger::class))->toBeTrue();
    expect(app()->bound(MultiChannelLogger::class))->toBeTrue();

    expect($cmd->checkPackageInstalledPublic())->toBeTrue();
    expect($cmd->checkServiceProviderPublic())->toBeTrue();
    expect($cmd->checkConfigFilePublic())->toBeTrue();
    expect($cmd->checkLoggingChannelsPublic())->toBeTrue();
    expect($cmd->checkMiddlewarePublic())->toBeTrue();
    expect($cmd->checkExceptionHandlingPublic())->toBeTrue();
    expect($cmd->checkContainerBindingsPublic())->toBeTrue();
});

test('VerifyInstallation checkLoggingChannels returns false when config/logging.php is missing', function () {
    $cmd = new TestVerifyInstallationCommand();

    $loggingPath = config_path('logging.php');
    $original = File::exists($loggingPath) ? File::get($loggingPath) : null;

    if (!File::exists($loggingPath)) {
        File::put($loggingPath, "<?php return [];\n");
    }

    File::delete($loggingPath);
    expect($cmd->checkLoggingChannelsPublic())->toBeFalse();

    if ($original !== null) {
        File::put($loggingPath, $original);
    } else {
        File::delete($loggingPath);
    }
});

test('VerifyInstallation checkMiddleware returns false when bootstrap/app.php is missing', function () {
    $cmd = new TestVerifyInstallationCommand();

    $appPath = base_path('bootstrap/app.php');
    $original = File::exists($appPath) ? File::get($appPath) : null;

    if (!File::exists($appPath)) {
        File::ensureDirectoryExists(dirname($appPath));
        File::put($appPath, "<?php\n// test bootstrap\n");
    }

    File::delete($appPath);
    expect($cmd->checkMiddlewarePublic())->toBeFalse();

    if ($original !== null) {
        File::put($appPath, $original);
    } else {
        File::delete($appPath);
    }
});

test('VerifyInstallation checkLoggingChannels reports missing kafka or opensearch', function () {
    $cmd = new TestVerifyInstallationCommand();

    $loggingPath = config_path('logging.php');
    File::ensureDirectoryExists(dirname($loggingPath));
    $original = File::exists($loggingPath) ? File::get($loggingPath) : null;

    // Missing kafka
    File::put($loggingPath, "// Ermetix\\LaravelLogger\\Logging\\CreateOpenSearchLogger\n");
    expect($cmd->checkLoggingChannelsPublic())->toBeFalse();

    // Missing opensearch
    File::put($loggingPath, "// Ermetix\\LaravelLogger\\Logging\\CreateKafkaLogger\n");
    expect($cmd->checkLoggingChannelsPublic())->toBeFalse();

    if ($original !== null) {
        File::put($loggingPath, $original);
    } else {
        File::delete($loggingPath);
    }
});

test('VerifyInstallation checkMiddleware reports missing individual middleware', function () {
    $cmd = new TestVerifyInstallationCommand();

    $appPath = base_path('bootstrap/app.php');
    File::ensureDirectoryExists(dirname($appPath));
    $original = File::exists($appPath) ? File::get($appPath) : null;

    File::put($appPath, "Ermetix\\LaravelLogger\\Http\\Middleware\\RequestId\n");
    expect($cmd->checkMiddlewarePublic())->toBeFalse();

    if ($original !== null) {
        File::put($appPath, $original);
    } else {
        File::delete($appPath);
    }
});

test('VerifyInstallation checkMiddleware reports missing RequestId', function () {
    $cmd = new TestVerifyInstallationCommand();

    $appPath = base_path('bootstrap/app.php');
    File::ensureDirectoryExists(dirname($appPath));
    $original = File::exists($appPath) ? File::get($appPath) : null;

    File::put($appPath, <<<'PHP'
<?php
// Ermetix\LaravelLogger\Http\Middleware\ApiAccessLog
// Ermetix\LaravelLogger\Http\Middleware\FlushDeferredLogs
PHP);

    expect($cmd->checkMiddlewarePublic())->toBeFalse();

    if ($original !== null) {
        File::put($appPath, $original);
    } else {
        File::delete($appPath);
    }
});

test('VerifyInstallation checkExceptionHandling reports missing exception handling', function () {
    $cmd = new TestVerifyInstallationCommand();

    $appPath = base_path('bootstrap/app.php');
    File::ensureDirectoryExists(dirname($appPath));
    $original = File::exists($appPath) ? File::get($appPath) : null;
    File::put($appPath, "<?php\n// no ErrorLogObject reference\n");

    expect($cmd->checkExceptionHandlingPublic())->toBeFalse();

    if ($original !== null) {
        File::put($appPath, $original);
    } else {
        File::delete($appPath);
    }
});

test('VerifyInstallation checkArtisanCommands can report missing commands', function () {
    $cmd = new TestVerifyInstallationCommand();

    $cmd->setApplication(new \Symfony\Component\Console\Application());

    expect($cmd->checkArtisanCommandsPublic())->toBeFalse();
});

test('VerifyInstallation checkContainerBindings can report missing binding', function () {
    $cmd = new TestVerifyInstallationCommand();

    // Simulate an app container where one binding is missing.
    $GLOBALS['__ll_app_override'] = new class {
        public function bound(string $abstract): bool
        {
            return $abstract !== DeferredLogger::class;
        }
    };

    expect($cmd->checkContainerBindingsPublic())->toBeFalse();

    unset($GLOBALS['__ll_app_override']);
});

test('VerifyInstallation displayResults prints all checks', function () {
    $cmd = new TestVerifyInstallationCommand();
    $cmd->displayResultsPublic([
        'package_installed' => true,
        'service_provider' => false,
        'config_file' => true,
        'logging_channels' => false,
        'middleware' => true,
        'exception_handling' => false,
        'artisan_commands' => true,
        'container_bindings' => false,
    ]);

    expect(true)->toBeTrue();
});

