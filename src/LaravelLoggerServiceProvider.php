<?php

namespace Ermetix\LaravelLogger;

use Illuminate\Support\ServiceProvider;
use Ermetix\LaravelLogger\Support\Logging\DeferredLogger;
use Ermetix\LaravelLogger\Support\Logging\MultiChannelLogger;
use Ermetix\LaravelLogger\Support\Logging\TypedLogger;

class LaravelLoggerServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Merge config
        $this->mergeConfigFrom(
            __DIR__.'/../config/laravel-logger.php',
            'laravel-logger'
        );

        // DeferredLogger is a singleton that accumulates logs in memory
        $this->app->singleton(DeferredLogger::class, function ($app) {
            $maxLogs = config('laravel-logger.deferred.max_logs', 1000);
            $warnOnLimit = config('laravel-logger.deferred.warn_on_limit', true);
            
            return new DeferredLogger(
                maxLogs: $maxLogs > 0 ? $maxLogs : null,
                warnOnLimit: $warnOnLimit
            );
        });

        $this->app->singleton(MultiChannelLogger::class, function ($app) {
            return new MultiChannelLogger(
                deferredLogger: $app->make(DeferredLogger::class),
            );
        });

        $this->app->singleton(TypedLogger::class, function () {
            return new TypedLogger(app(MultiChannelLogger::class));
        });

        // Facade accessor
        $this->app->singleton('laravel_logger', function () {
            return app(TypedLogger::class);
        });

        // Register listeners so they can be resolved via app()
        // Using bind() with explicit closure to avoid autowiring issues
        $this->app->bind(\Ermetix\LaravelLogger\Listeners\LogDatabaseQuery::class, function () {
            return new \Ermetix\LaravelLogger\Listeners\LogDatabaseQuery();
        });
        $this->app->bind(\Ermetix\LaravelLogger\Listeners\LogModelEvents::class, function () {
            return new \Ermetix\LaravelLogger\Listeners\LogModelEvents();
        });
        $this->app->bind(\Ermetix\LaravelLogger\Listeners\PropagateRequestIdToJob::class, function () {
            return new \Ermetix\LaravelLogger\Listeners\PropagateRequestIdToJob();
        });
        $this->app->bind(\Ermetix\LaravelLogger\Listeners\FlushDeferredLogsForJob::class, function ($app) {
            return new \Ermetix\LaravelLogger\Listeners\FlushDeferredLogsForJob(
                $app->make(\Ermetix\LaravelLogger\Support\Logging\DeferredLogger::class)
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Always flush deferred logs at the end of the lifecycle (HTTP request and CLI).
        // This is especially important because the default for $defer is true.
        $this->app->terminating(function (): void {
            if ($this->app->bound(DeferredLogger::class)) {
                $this->app->make(DeferredLogger::class)->flush();
            }
        });

        // Publish config
        $this->publishes([
            __DIR__.'/../config/laravel-logger.php' => config_path('laravel-logger.php'),
        ], 'laravel-logger-config');

        // Publish OpenSearch templates
        $this->publishes([
            __DIR__.'/../resources/opensearch' => base_path('opensearch'),
        ], 'laravel-logger-opensearch');

        // Publish Docker setup (scripts + docker-compose)
        $this->publishes([
            __DIR__.'/../docker/opensearch' => base_path('docker/opensearch'),
            __DIR__.'/../docker/kafka' => base_path('docker/kafka'),
        ], 'laravel-logger-docker');

        // Register middleware
        $this->registerMiddleware();

        // Register Artisan commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Ermetix\LaravelLogger\Console\Commands\InstallLogger::class,
                \Ermetix\LaravelLogger\Console\Commands\VerifyInstallation::class,
                \Ermetix\LaravelLogger\Console\Commands\TestOpenSearchLogging::class,
                \Ermetix\LaravelLogger\Console\Commands\VerifyOpenSearchData::class,
                \Ermetix\LaravelLogger\Console\Commands\TestKafkaLogging::class,
            ]);
        }

        // Automatically log database queries to orm_log
        if (config('laravel-logger.orm.enabled', false)) {
            $app = $this->app;
            \Illuminate\Support\Facades\Event::listen(
                \Illuminate\Database\Events\QueryExecuted::class,
                function (\Illuminate\Database\Events\QueryExecuted $event) use ($app) {
                    try {
                        $listener = $app->make(\Ermetix\LaravelLogger\Listeners\LogDatabaseQuery::class);
                        $listener->handle($event);
                    } catch (\Throwable $e) {
                        // Silently fail to avoid breaking the application
                        \Illuminate\Support\Facades\Log::error('Failed to log database query', [
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                    }
                },
            );
        }

        // Automatically log Eloquent model events (creating, created, updating, updated, deleting, deleted)
        if (config('laravel-logger.orm.model_events_enabled', false)) {
            // In Laravel 12 the base Eloquent Model is abstract, so we can't call Model::observe(...)
            // (it would try to instantiate the abstract class). Instead we listen to wildcard
            // Eloquent events and forward them to our listener.
            \Illuminate\Support\Facades\Event::listen('eloquent.created: *', function (string $_eventName, array $data): void {
                $model = $data[0] ?? null;
                if ($model instanceof \Illuminate\Database\Eloquent\Model) {
                    app(\Ermetix\LaravelLogger\Listeners\LogModelEvents::class)->created($model);
                }
            });

            \Illuminate\Support\Facades\Event::listen('eloquent.updated: *', function (string $_eventName, array $data): void {
                $model = $data[0] ?? null;
                if ($model instanceof \Illuminate\Database\Eloquent\Model) {
                    app(\Ermetix\LaravelLogger\Listeners\LogModelEvents::class)->updated($model);
                }
            });

            \Illuminate\Support\Facades\Event::listen('eloquent.deleted: *', function (string $_eventName, array $data): void {
                $model = $data[0] ?? null;
                if ($model instanceof \Illuminate\Database\Eloquent\Model) {
                    app(\Ermetix\LaravelLogger\Listeners\LogModelEvents::class)->deleted($model);
                }
            });
        }

        // Propagate request_id to jobs as parent_request_id
        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Queue\Events\JobProcessing::class,
            \Ermetix\LaravelLogger\Listeners\PropagateRequestIdToJob::class,
        );

        // Flush deferred logs after jobs complete
        \Illuminate\Support\Facades\Event::listen(
            [\Illuminate\Queue\Events\JobProcessed::class, \Illuminate\Queue\Events\JobFailed::class],
            \Ermetix\LaravelLogger\Listeners\FlushDeferredLogsForJob::class,
        );

        // Register shutdown handler to flush logs on fatal errors
        register_shutdown_function(function (): void {
            $this->handleShutdown();
        });
    }

    /**
     * Register middleware.
     */
    protected function registerMiddleware(): void
    {
        // Middleware can be registered in bootstrap/app.php or via config
        // This is just a helper method if needed
    }

    /**
     * Handle application shutdown and flush logs on fatal errors.
     *
     * Extracted into a method to keep the shutdown callback minimal and testable.
     */
    protected function handleShutdown(): void
    {
        $error = error_get_last();

        if ($error === null) {
            return;
        }

        if (!in_array($error['type'] ?? null, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_RECOVERABLE_ERROR], true)) {
            return;
        }

        // Fatal error occurred, log it immediately and flush deferred logs
        try {
            // Log the fatal error immediately (bypass deferred)
            if (app()->bound(TypedLogger::class)) {
                $request = request();

                \Ermetix\LaravelLogger\Facades\LaravelLogger::error(new \Ermetix\LaravelLogger\Support\Logging\Objects\ErrorLogObject(
                    message: $error['message'] ?? 'Fatal error',
                    stackTrace: "Fatal error in {$error['file']} on line {$error['line']}",
                    exceptionClass: 'FatalError',
                    file: $error['file'] ?? 'unknown',
                    line: $error['line'] ?? 0,
                    code: $error['type'] ?? 0,
                    userId: \Illuminate\Support\Facades\Auth::check() ? (string) \Illuminate\Support\Facades\Auth::id() : null,
                    route: $request?->route()?->getName(),
                    method: $request?->getMethod(),
                    url: $request?->fullUrl(),
                    level: 'critical',
                ), defer: false); // Write fatal errors immediately
            }

            // Flush deferred logs immediately
            if (app()->bound(DeferredLogger::class)) {
                app(DeferredLogger::class)->flush();
            }
        } catch (\Throwable $e) {
            // Ignore errors during shutdown
        }
    }
}
