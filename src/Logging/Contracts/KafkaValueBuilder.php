<?php

namespace Ermetix\LaravelLogger\Logging\Contracts;

use Monolog\LogRecord;

interface KafkaValueBuilder
{
    /**
     * Build the JSON object that will be sent as Kafka record "value".
     *
     * @return array<string, mixed>
     */
    public function __invoke(LogRecord $record): array;
}
