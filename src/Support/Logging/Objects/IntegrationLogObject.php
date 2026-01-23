<?php

namespace Ermetix\LaravelLogger\Support\Logging\Objects;

class IntegrationLogObject extends BaseLogObject
{
    public function __construct(
        string $message,
        public readonly ?string $integrationName = null,
        public readonly ?string $url = null,
        public readonly ?string $method = null,
        public readonly ?int $status = null,
        public readonly ?int $durationMs = null,
        public readonly ?string $externalId = null,
        public readonly ?string $correlationId = null,
        public readonly ?int $attempt = null,
        public readonly ?int $maxAttempts = null,
        public readonly ?int $requestSizeBytes = null,
        public readonly ?int $responseSizeBytes = null,
        public readonly ?string $requestBody = null,
        public readonly ?string $responseBody = null,
        public readonly ?array $headers = null,
        public readonly ?string $errorMessage = null,
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
        return 'integration_log';
    }

    public function toArray(): array
    {
        // integration_log doesn't need source location fields (file/line/class/function)
        // as they would always point to the point where Log::integration() is called,
        // not the actual code that triggered the integration (usually a service class)
        return array_merge(
            $this->getCommonFields(includeSourceLocation: false),
            array_filter([
                'integration_name' => $this->integrationName,
                'url' => $this->url,
                'method' => $this->method,
                'status' => $this->status,
                'duration_ms' => $this->durationMs,
                'external_id' => $this->externalId,
                'correlation_id' => $this->correlationId,
                'attempt' => $this->attempt,
                'max_attempts' => $this->maxAttempts,
                'request_size_bytes' => $this->requestSizeBytes,
                'response_size_bytes' => $this->responseSizeBytes,
                // Format JSON bodies with pretty printing for better readability in OpenSearch Dashboards
                'request_body' => $this->formatJsonIfValid($this->requestBody),
                'response_body' => $this->formatJsonIfValid($this->responseBody),
                // Format headers array as pretty-printed JSON
                'headers' => $this->formatArrayAsJson($this->headers),
                'error_message' => $this->errorMessage,
            ], fn ($value) => $value !== null)
        );
    }
}
