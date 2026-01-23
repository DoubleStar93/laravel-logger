<?php

namespace Ermetix\LaravelLogger\Logging\Handlers;

use Ermetix\LaravelLogger\Logging\Contracts\BatchableHandler;
use Ermetix\LaravelLogger\Logging\Contracts\KafkaValueBuilder;
use Ermetix\LaravelLogger\Support\Logging\LevelNormalizer;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

class KafkaRestProxyHandler extends AbstractProcessingHandler implements BatchableHandler
{
    private Client $http;

    public function __construct(
        private readonly string $restProxyUrl,
        private readonly string $topic,
        private readonly KafkaValueBuilder $valueBuilder,
        string|Level $level = 'debug',
        private readonly int $timeout = 2,
        private readonly bool $silent = true,
        ?Client $http = null,
        bool $bubble = true,
    ) {
        parent::__construct(LevelNormalizer::normalize($level), $bubble);

        $this->http = $http ?? new Client([
            'timeout' => $this->timeout,
        ]);
    }

    protected function write(LogRecord $record): void
    {
        $url = rtrim($this->restProxyUrl, '/').'/topics/'.$this->topic;

        $value = ($this->valueBuilder)($record);

        $payload = [
            'records' => [
                [
                    'value' => $value,
                ],
            ],
        ];

        try {
            $this->http->post($url, [
                'headers' => [
                    // Kafka REST Proxy JSON v2
                    'Content-Type' => 'application/vnd.kafka.json.v2+json',
                    'Accept' => 'application/vnd.kafka.v2+json',
                ],
                'json' => $payload,
            ]);
        } catch (GuzzleException $e) {
            if ($this->silent) {
                return;
            }

            throw $e;
        }
    }

    /**
     * Write multiple log records in a single batch to Kafka.
     * 
     * @param array<int, LogRecord> $records
     */
    public function writeBatch(array $records): void
    {
        if (empty($records)) {
            return;
        }

        $url = rtrim($this->restProxyUrl, '/').'/topics/'.$this->topic;

        // Build records array with all values
        $kafkaRecords = [];
        foreach ($records as $record) {
            $value = ($this->valueBuilder)($record);
            $kafkaRecords[] = [
                'value' => $value,
            ];
        }

        $payload = [
            'records' => $kafkaRecords,
        ];

        try {
            $this->http->post($url, [
                'headers' => [
                    // Kafka REST Proxy JSON v2
                    'Content-Type' => 'application/vnd.kafka.json.v2+json',
                    'Accept' => 'application/vnd.kafka.v2+json',
                ],
                'json' => $payload,
            ]);
        } catch (GuzzleException $e) {
            if ($this->silent) {
                return;
            }

            throw $e;
        }
    }
}
