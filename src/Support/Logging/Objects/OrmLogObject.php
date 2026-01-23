<?php

namespace Ermetix\LaravelLogger\Support\Logging\Objects;

class OrmLogObject extends BaseLogObject
{
    public function __construct(
        string $message,
        public readonly ?string $model = null,
        public readonly ?string $modelId = null,
        public readonly ?string $action = null,
        public readonly ?string $query = null,
        public readonly ?string $queryType = null,
        public readonly ?bool $isSlowQuery = null,
        public readonly ?int $durationMs = null,
        public readonly ?string $bindings = null,
        public readonly ?string $connection = null,
        public readonly ?string $table = null,
        public readonly ?string $transactionId = null,
        public readonly ?string $userId = null,
        public readonly ?array $previousValue = null,
        public readonly ?array $afterValue = null,
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
        return 'orm_log';
    }

    public function toArray(): array
    {
        // orm_log doesn't need source location fields (file/line/class/function)
        // as it's created automatically by LogDatabaseQuery listener, so file/line
        // would always point to the listener, not the actual code that executed the query
        return array_merge(
            $this->getCommonFields(includeSourceLocation: false),
            array_filter([
                'model' => $this->model,
                'model_id' => $this->modelId,
                'action' => $this->action,
                'query' => $this->query,
                'query_type' => $this->queryType,
                'is_slow_query' => $this->isSlowQuery,
                'duration_ms' => $this->durationMs,
                'bindings' => $this->bindings,
                'connection' => $this->connection,
                'table' => $this->table,
                'transaction_id' => $this->transactionId,
                'user_id' => $this->userId,
                'previous_value' => $this->previousValue,
                'after_value' => $this->afterValue,
            ], fn ($value) => $value !== null)
        );
    }
}
