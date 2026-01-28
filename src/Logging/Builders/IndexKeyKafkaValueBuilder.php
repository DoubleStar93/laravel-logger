<?php

namespace Ermetix\LaravelLogger\Logging\Builders;

use Ermetix\LaravelLogger\Logging\Contracts\KafkaValueBuilder;
use Monolog\LogRecord;

/**
 * Builds Kafka record.value as:
 *   { "<log_index>": { ...LogObjectFields, "@timestamp": "..." } }
 *
 * - Uses $record->context (populated by MultiChannelLogger) as the document source.
 * - Avoids Monolog metadata (channel/extra/etc) in the value.
 */
class IndexKeyKafkaValueBuilder implements KafkaValueBuilder
{
    public function __invoke(LogRecord $record): array
    {
        $index = $record->context['log_index'] ?? 'general_log';
        if (!is_string($index) || $index === '') {
            $index = 'general_log';
        }

        $doc = $record->context;
        unset($doc['log_index']);

        // Keep a stable timestamp field for downstream ingestion.
        $doc['@timestamp'] = $record->datetime->format('Y-m-d\TH:i:s.uP');

        return [
            $index => $doc,
        ];
    }
}

