<?php

namespace Ermetix\LaravelLogger\Logging;

use Ermetix\LaravelLogger\Logging\Builders\DefaultOpenSearchDocumentBuilder;
use Ermetix\LaravelLogger\Logging\Contracts\OpenSearchDocumentBuilder;
use Ermetix\LaravelLogger\Logging\Handlers\OpenSearchIndexHandler;
use Ermetix\LaravelLogger\Support\Config\ConfigReader;
use Illuminate\Log\Context\ContextLogProcessor;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;

class CreateOpenSearchLogger
{
    /**
     * Expected config keys (see config/logging.php):
     * - url (string)
     * - index (string) Fallback index if builder does not specify one
     * - username (string|null)
     * - password (string|null)
     * - verify_tls (bool)
     * - timeout (int)
     * - level (string)
     * - silent (bool)
     * - document_builder (class-string<OpenSearchDocumentBuilder>)
     */
    public function __invoke(array $config): Logger
    {
        $logger = new Logger('opensearch');

        // Validate and get document builder class with fallback
        $builderClass = ConfigReader::getClass('builders.opensearch', DefaultOpenSearchDocumentBuilder::class);
        if (isset($config['document_builder']) && is_string($config['document_builder']) && class_exists($config['document_builder'])) {
            $builderClass = $config['document_builder'];
        }
        /** @var OpenSearchDocumentBuilder $builder */
        $builder = app($builderClass);

        // Validate URL with fallback
        $baseUrl = ConfigReader::getUrl('opensearch.url', 'http://localhost:9200');
        if (isset($config['url']) && filter_var($config['url'], FILTER_VALIDATE_URL)) {
            $baseUrl = $config['url'];
        }

        // Validate index with fallback
        $index = ConfigReader::getString('opensearch.default_index', 'general_log', allowEmpty: false);
        if (isset($config['index']) && is_string($config['index']) && $config['index'] !== '') {
            $index = $config['index'];
        }

        // Validate timeout with fallback (min 1, max 60 seconds)
        $timeout = ConfigReader::getInt('opensearch.timeout', 2, min: 1, max: 60);
        if (isset($config['timeout'])) {
            $timeoutValue = is_int($config['timeout']) ? $config['timeout'] : (int) $config['timeout'];
            if ($timeoutValue >= 1 && $timeoutValue <= 60) {
                $timeout = $timeoutValue;
            }
        }

        // Validate boolean flags with fallback
        $silent = ConfigReader::getBool('opensearch.silent', true);
        if (isset($config['silent'])) {
            $silent = filter_var($config['silent'], FILTER_VALIDATE_BOOL);
        }

        $verifyTls = ConfigReader::getBool('opensearch.verify_tls', true);
        if (isset($config['verify_tls'])) {
            $verifyTls = filter_var($config['verify_tls'], FILTER_VALIDATE_BOOL);
        }

        // Get credentials (can be null)
        $username = $config['username'] ?? ConfigReader::getString('opensearch.username', null);
        $password = $config['password'] ?? ConfigReader::getString('opensearch.password', null);

        $logger->pushHandler(new OpenSearchIndexHandler(
            baseUrl: $baseUrl,
            index: $index,
            documentBuilder: $builder,
            username: $username,
            password: $password,
            level: $config['level'] ?? 'debug',
            timeout: $timeout,
            silent: $silent,
            verifyTls: $verifyTls,
        ));

        $logger->pushProcessor(new PsrLogMessageProcessor());
        $logger->pushProcessor(new ContextLogProcessor());

        return $logger;
    }
}
