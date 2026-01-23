<?php

namespace Ermetix\LaravelLogger\Support\Logging;

use Monolog\Level;

/**
 * Utility class to normalize log levels from string to Monolog Level enum.
 * 
 * This avoids code duplication across multiple handler classes.
 */
class LevelNormalizer
{
    /**
     * Normalize a log level from string or Level enum to Level enum.
     * 
     * @param string|Level $level The log level (e.g., 'debug', 'info', 'warning', 'error')
     * @return Level The normalized Monolog Level enum
     */
    public static function normalize(string|Level $level): Level
    {
        if ($level instanceof Level) {
            return $level;
        }

        // config/logging.php typically provides "debug", "info", "warning", "error", etc.
        return Level::fromName($level);
    }
}
