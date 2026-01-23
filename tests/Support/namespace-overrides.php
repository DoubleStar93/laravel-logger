<?php

/**
 * Namespace-level function overrides used for testing.
 *
 * These let us:
 * - avoid real sleeps/network in console commands
 * - capture shutdown callbacks registered by the ServiceProvider
 *
 * Each override delegates to the global function unless a test sets a control
 * flag in $GLOBALS (see below).
 */

namespace Ermetix\LaravelLogger {
    function register_shutdown_function(callable $callback): void
    {
        if (!empty($GLOBALS['__ll_capture_shutdown'])) {
            $GLOBALS['__ll_shutdown_callbacks'] ??= [];
            $GLOBALS['__ll_shutdown_callbacks'][] = $callback;
            return;
        }

        \register_shutdown_function($callback);
    }

    function error_get_last(): ?array
    {
        if (array_key_exists('__ll_error_get_last', $GLOBALS)) {
            return $GLOBALS['__ll_error_get_last'];
        }

        return \error_get_last();
    }

    function request()
    {
        if (array_key_exists('__ll_request', $GLOBALS)) {
            return $GLOBALS['__ll_request'];
        }

        return \request();
    }
}

namespace Ermetix\LaravelLogger\Console\Commands {
    function app($abstract = null, array $parameters = [])
    {
        if (array_key_exists('__ll_app_override', $GLOBALS) && $GLOBALS['__ll_app_override'] !== null) {
            return $GLOBALS['__ll_app_override'];
        }

        return \app($abstract, $parameters);
    }

    function class_exists(string $class, bool $autoload = true): bool
    {
        if (!empty($GLOBALS['__ll_class_exists']) && is_array($GLOBALS['__ll_class_exists'])) {
            if (array_key_exists($class, $GLOBALS['__ll_class_exists'])) {
                return (bool) $GLOBALS['__ll_class_exists'][$class];
            }
        }

        return \class_exists($class, $autoload);
    }

    function sleep(int $seconds): int
    {
        if (!empty($GLOBALS['__ll_disable_sleep'])) {
            return 0;
        }

        return \sleep($seconds);
    }

    function dispatch(object $job)
    {
        if (!empty($GLOBALS['__ll_dispatch_throw'])) {
            throw new \RuntimeException('dispatch failed (test)');
        }

        $GLOBALS['__ll_dispatched_jobs'] ??= [];
        $GLOBALS['__ll_dispatched_jobs'][] = $job;

        // Return something that behaves like a dispatch return value.
        return $job;
    }

    function dispatch_sync(object $job)
    {
        if (!empty($GLOBALS['__ll_dispatch_sync_throw'])) {
            throw new \RuntimeException('dispatch_sync failed (test)');
        }

        $GLOBALS['__ll_dispatch_sync_jobs'] ??= [];
        $GLOBALS['__ll_dispatch_sync_jobs'][] = $job;

        return $job;
    }

    function stream_context_create(array $options = [], array $params = [])
    {
        if (!empty($GLOBALS['__ll_stream_context'])) {
            return $GLOBALS['__ll_stream_context'];
        }

        return \stream_context_create($options, $params);
    }

    function file_get_contents(string $filename, bool $use_include_path = false, $context = null, int $offset = 0, ?int $length = null): string|false
    {
        if (!empty($GLOBALS['__ll_file_get_contents_map']) && is_array($GLOBALS['__ll_file_get_contents_map'])) {
            if (array_key_exists($filename, $GLOBALS['__ll_file_get_contents_map'])) {
                return $GLOBALS['__ll_file_get_contents_map'][$filename];
            }
        }

        if ($length === null) {
            return \file_get_contents($filename, $use_include_path, $context, $offset);
        }

        return \file_get_contents($filename, $use_include_path, $context, $offset, $length);
    }
}

namespace Ermetix\LaravelLogger\Logging\Builders {
    function session()
    {
        if (!empty($GLOBALS['__ll_throw_session'])) {
            throw new \RuntimeException('session not available (test)');
        }

        return \session();
    }
}

namespace Ermetix\LaravelLogger\Logging\Handlers {
    function storage_path(string $path = ''): string
    {
        if (!empty($GLOBALS['__ll_storage_path_override'])) {
            return (string) $GLOBALS['__ll_storage_path_override'];
        }

        if (!empty($GLOBALS['__ll_throw_storage_path'])) {
            throw new \RuntimeException('storage_path failed (test)');
        }

        return \storage_path($path);
    }
}

namespace Ermetix\LaravelLogger\Support\Logging {
    function storage_path(string $path = ''): string
    {
        if (!empty($GLOBALS['__ll_throw_storage_path_support'])) {
            throw new \RuntimeException('storage_path failed (test)');
        }

        return \storage_path($path);
    }

    function json_encode(mixed $value, int $flags = 0, int $depth = 512): string|false
    {
        if (!empty($GLOBALS['__ll_force_json_encode_fail'])) {
            return false;
        }

        return \json_encode($value, $flags, $depth);
    }
}

