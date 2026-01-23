<?php

namespace Ermetix\LaravelLogger\Support\Logging\Objects;

class ErrorLogObject extends BaseLogObject
{
    public function __construct(
        string $message,
        public readonly ?string $stackTrace = null,
        public readonly ?string $exceptionClass = null,
        ?string $file = null,
        ?int $line = null,
        public readonly ?int $code = null,
        public readonly ?array $previousException = null,
        public readonly ?string $userId = null,
        public readonly ?string $route = null,
        public readonly ?string $method = null,
        public readonly ?string $url = null,
        string $level = 'error',
        // Common fields
        ?string $parentRequestId = null,
        ?string $traceId = null,
        ?string $spanId = null,
        ?string $sessionId = null,
        ?string $environment = null,
        ?string $hostname = null,
        ?string $serviceName = null,
        ?string $appVersion = null,
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
        return 'error_log';
    }

    public function toArray(): array
    {
        // error_log doesn't need file/line/class/function fields from BaseLogObject
        // as stack_trace already contains all this information for every frame
        return array_merge(
            $this->getCommonFields(includeSourceLocation: false),
            array_filter([
                'stack_trace' => $this->stackTrace,
                'exception_class' => $this->exceptionClass,
                'code' => $this->code,
                'previous_exception' => $this->previousException,

                // Flattened request context fields (error_log only)
                // These replace the old nested "context" object.
                'context_user_id' => $this->userId,
                'context_route' => $this->route,
                'context_method' => $this->method,
                'context_url' => $this->url,
            ], fn ($value) => $value !== null)
        );
    }
}
