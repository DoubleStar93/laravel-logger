<?php

namespace Ermetix\LaravelLogger\Support\Config;

/**
 * Centralized config reader with validation and safe fallbacks.
 * 
 * This class provides type-safe access to package configuration with
 * sensible defaults when values are missing or invalid.
 */
class ConfigReader
{
    /**
     * Get a config value with fallback to default.
     * 
     * @param string $key Config key (dot notation supported, e.g., 'orm.enabled')
     * @param mixed $default Default value if config is missing or invalid
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = config("laravel-logger.{$key}");

        // Return default if value is null or empty string (but allow 0, false, empty array)
        if ($value === null || $value === '') {
            return $default;
        }

        return $value;
    }

    /**
     * Get a boolean config value with validation.
     * 
     * @param string $key Config key
     * @param bool $default Default value
     * @return bool
     */
    public static function getBool(string $key, bool $default = false): bool
    {
        $value = self::get($key, $default);

        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return filter_var($value, FILTER_VALIDATE_BOOL);
        }

        if (is_int($value)) {
            return $value !== 0;
        }

        return $default;
    }

    /**
     * Get an integer config value with validation.
     * 
     * @param string $key Config key
     * @param int $default Default value
     * @param int|null $min Minimum allowed value (null = no minimum)
     * @param int|null $max Maximum allowed value (null = no maximum)
     * @return int
     */
    public static function getInt(string $key, int $default = 0, ?int $min = null, ?int $max = null): int
    {
        $value = self::get($key, $default);

        if (is_int($value)) {
            $value = $value;
        } elseif (is_string($value) && is_numeric($value)) {
            $value = (int) $value;
        } else {
            return $default;
        }

        if ($min !== null && $value < $min) {
            return $default;
        }

        if ($max !== null && $value > $max) {
            return $default;
        }

        return $value;
    }

    /**
     * Get a string config value with validation.
     * 
     * @param string $key Config key
     * @param string|null $default Default value
     * @param bool $allowEmpty Whether to allow empty strings (if false, returns default)
     * @return string|null
     */
    public static function getString(string $key, ?string $default = null, bool $allowEmpty = true): ?string
    {
        $value = self::get($key, $default);

        if (!is_string($value)) {
            return $default;
        }

        // Note: empty strings are already filtered by get(), so allowEmpty check is not needed
        return $value;
    }

    /**
     * Get an array config value with validation.
     * 
     * @param string $key Config key
     * @param array $default Default value
     * @return array
     */
    public static function getArray(string $key, array $default = []): array
    {
        $value = self::get($key, $default);

        if (!is_array($value)) {
            return $default;
        }

        return $value;
    }

    /**
     * Get a class name config value with validation.
     * 
     * @param string $key Config key
     * @param string $default Default class name
     * @return string
     */
    public static function getClass(string $key, string $default): string
    {
        $value = self::getString($key, $default, allowEmpty: false);

        if ($value === null || !class_exists($value)) {
            return $default;
        }

        return $value;
    }

    /**
     * Get a URL config value with validation.
     * 
     * @param string $key Config key
     * @param string $default Default URL
     * @return string
     */
    public static function getUrl(string $key, string $default): string
    {
        $value = self::getString($key, $default, allowEmpty: false);

        // Note: getString() with non-null default never returns null, so null check is not needed

        // Basic URL validation
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            return $default;
        }

        return $value;
    }
}
