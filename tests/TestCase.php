<?php

namespace Ermetix\LaravelLogger\Tests;

use Ermetix\LaravelLogger\LaravelLoggerServiceProvider;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Testbench ships minimal config files; ensure realistic defaults so the
        // install/verify commands can operate during tests.
        $loggingPath = config_path('logging.php');
        if (!File::exists($loggingPath) || trim((string) File::get($loggingPath)) === '<?php return [];') {
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
        }

        $appPath = base_path('bootstrap/app.php');
        if (!File::exists($appPath) || !str_contains((string) File::get($appPath), '->create()')) {
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
    }

    /**
     * Get package providers.
     */
    protected function getPackageProviders($app): array
    {
        return [
            LaravelLoggerServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     */
    protected function defineEnvironment($app): void
    {
        // Setup default environment variables
        $app['config']->set('logging.channels.stack.channels', ['single']);
        $app['config']->set('logging.channels.opensearch', [
            'driver' => 'custom',
            'via' => \Ermetix\LaravelLogger\Logging\CreateOpenSearchLogger::class,
            'level' => 'debug',
            'url' => 'http://localhost:9200',
            'index' => 'test_log',
            'silent' => true,
        ]);
        
        // Setup laravel-logger config
        $app['config']->set('laravel-logger', [
            'service_name' => 'test-service',
            'orm' => [
                'enabled' => true,
                'model_events_enabled' => true,
                'slow_query_threshold_ms' => 1000,
                'ignore_patterns' => [],
            ],
        ]);
    }
}
