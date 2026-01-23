<?php

namespace Ermetix\LaravelLogger\Support\Logging\Objects;

class GeneralLogObject extends BaseLogObject
{
    public function __construct(
        string $message,
        public readonly ?string $event = null,
        public readonly ?string $userId = null,
        public readonly ?string $entityType = null,
        public readonly ?string $entityId = null,
        public readonly ?string $feature = null,
        public readonly ?string $actionType = null,
        string $level = 'info',
        // Common fields
        ?string $parentRequestId = null,
        ?string $traceId = null,
        ?string $spanId = null,
        ?string $sessionId = null,
        ?string $environment = null,
        ?string $hostname = null,
        ?string $serviceName = null,
        ?string $appVersion = null,
        ?string $file = null,
        ?int $line = null,
        ?string $class = null,
        ?string $function = null,
        ?array $tags = null,
    ) {
        parent::__construct(
            $message,
            $level,
            $parentRequestId,
            $traceId,
            $spanId,
            $sessionId,
            $environment,
            $hostname,
            $serviceName,
            $appVersion,
            $file,
            $line,
            $class,
            $function,
            $tags
        );
    }

    public function index(): string
    {
        return 'general_log';
    }

    public function toArray(): array
    {
        // general_log includes message field for human-readable descriptions
        return array_merge(
            $this->getCommonFields(includeSourceLocation: true, includeMessage: true),
            array_filter([
                'event' => $this->event,
                'user_id' => $this->userId,
                'entity_type' => $this->entityType,
                'entity_id' => $this->entityId,
                'feature' => $this->feature,
                'action_type' => $this->actionType,
            ], fn ($value) => $value !== null)
        );
    }
}
