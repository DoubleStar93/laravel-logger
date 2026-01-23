<?php

namespace Ermetix\LaravelLogger\Logging;

use Ermetix\LaravelLogger\Logging\Handlers\IndexFileHandler;
use Ermetix\LaravelLogger\Support\Config\ConfigReader;
use Illuminate\Log\Context\ContextLogProcessor;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;

class CreateIndexFileLogger
{
    /**
     * Create the "index_file" logger instance.
     *
     * Expected config keys (see config/logging.php):
     * - level (string)
     */
    public function __invoke(array $config): Logger
    {
        $logger = new Logger('index_file');

        $logger->pushHandler(new IndexFileHandler(
            level: $config['level'] ?? 'debug',
        ));

        // Enables {foo} placeholder replacement in messages using context.
        $logger->pushProcessor(new PsrLogMessageProcessor());
        // Adds Illuminate\Support\Facades\Context data into $record->extra.
        $logger->pushProcessor(new ContextLogProcessor());

        return $logger;
    }
}

