<?php

namespace Ermetix\LaravelLogger\Logging\Builders;

use Ermetix\LaravelLogger\Logging\Contracts\KafkaValueBuilder;
use Monolog\LogRecord;

class DefaultKafkaValueBuilder implements KafkaValueBuilder
{
    public function __invoke(LogRecord $record): array
    {
        return [
            'timestamp' => $record->datetime->format(DATE_ATOM),
            'level' => $record->level->getName(),
            'channel' => $record->channel,
            'message' => $record->message,
            'context' => $record->context,
            'extra' => $record->extra,
        ];
    }
}
