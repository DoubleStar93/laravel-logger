<?php

namespace Ermetix\LaravelLogger\Logging;

use Ermetix\LaravelLogger\Logging\Builders\DefaultKafkaValueBuilder;
use Ermetix\LaravelLogger\Logging\Contracts\KafkaValueBuilder;
use Ermetix\LaravelLogger\Logging\Handlers\KafkaRestProxyHandler;
use Ermetix\LaravelLogger\Support\Config\ConfigReader;
use Illuminate\Log\Context\ContextLogProcessor;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;

class CreateKafkaLogger
{
    /**
     * Create a Kafka logger instance.
     *
     * Expected config keys (see config/logging.php):
     * - rest_proxy_url (string)
     * - topic (string)
     * - timeout (int)
     * - level (string)
     * - silent (bool)
     */
    public function __invoke(array $config): Logger
    {
        $logger = new Logger('kafka');

        // Validate and get value builder class with fallback
        $valueBuilderClass = ConfigReader::getClass('builders.kafka', DefaultKafkaValueBuilder::class);
        if (isset($config['value_builder']) && is_string($config['value_builder']) && class_exists($config['value_builder'])) {
            $valueBuilderClass = $config['value_builder'];
        }
        /** @var KafkaValueBuilder $valueBuilder */
        $valueBuilder = app($valueBuilderClass);

        // Validate URL with fallback
        $restProxyUrl = ConfigReader::getUrl('kafka.rest_proxy_url', 'http://localhost:8082');
        if (isset($config['rest_proxy_url']) && filter_var($config['rest_proxy_url'], FILTER_VALIDATE_URL)) {
            $restProxyUrl = $config['rest_proxy_url'];
        }

        // Validate topic with fallback
        $topic = ConfigReader::getString('kafka.topic', 'laravel-logs', allowEmpty: false);
        if (isset($config['topic']) && is_string($config['topic']) && $config['topic'] !== '') {
            $topic = $config['topic'];
        }

        // Validate timeout with fallback (min 1, max 60 seconds)
        $timeout = ConfigReader::getInt('kafka.timeout', 2, min: 1, max: 60);
        if (isset($config['timeout'])) {
            $timeoutValue = is_int($config['timeout']) ? $config['timeout'] : (int) $config['timeout'];
            if ($timeoutValue >= 1 && $timeoutValue <= 60) {
                $timeout = $timeoutValue;
            }
        }

        // Validate boolean flag with fallback
        $silent = ConfigReader::getBool('kafka.silent', true);
        if (isset($config['silent'])) {
            $silent = filter_var($config['silent'], FILTER_VALIDATE_BOOL);
        }

        $logger->pushHandler(new KafkaRestProxyHandler(
            restProxyUrl: $restProxyUrl,
            topic: $topic,
            valueBuilder: $valueBuilder,
            level: $config['level'] ?? 'debug',
            timeout: $timeout,
            silent: $silent,
        ));

        // Enables {foo} placeholder replacement in messages using context.
        $logger->pushProcessor(new PsrLogMessageProcessor());
        // Adds Illuminate\Support\Facades\Context data into $record->extra.
        $logger->pushProcessor(new ContextLogProcessor());

        return $logger;
    }
}
