<?php

namespace Ermetix\LaravelLogger\Support\Logging\Objects;

class ApiLogObject extends BaseLogObject
{
    public function __construct(
        string $message,
        public readonly ?string $method = null,
        public readonly ?string $path = null,
        public readonly ?string $routeName = null,
        public readonly ?int $status = null,
        public readonly ?int $durationMs = null,
        public readonly ?string $ip = null,
        public readonly ?string $userId = null,
        public readonly ?string $userAgent = null,
        public readonly ?string $referer = null,
        public readonly ?string $queryString = null,
        public readonly ?int $requestSizeBytes = null,
        public readonly ?int $responseSizeBytes = null,
        public readonly ?string $authenticationMethod = null,
        public readonly ?string $apiVersion = null,
        public readonly ?string $correlationId = null,
        public readonly ?string $requestBody = null,
        public readonly ?string $responseBody = null,
        public readonly ?array $requestHeaders = null,
        public readonly ?array $responseHeaders = null,
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
        return 'api_log';
    }

    public function toArray(): array
    {
        // api_log doesn't need source location fields (file/line/class/function)
        // as they would always point to the middleware, which is not useful
        return array_merge(
            $this->getCommonFields(includeSourceLocation: false),
            array_filter([
                'method' => $this->method,
                'path' => $this->path,
                'route_name' => $this->routeName,
                'status' => $this->status,
                'duration_ms' => $this->durationMs,
                'ip' => $this->ip,
                'user_id' => $this->userId,
                'user_agent' => $this->userAgent,
                'referer' => $this->referer,
                'query_string' => $this->queryString,
                'request_size_bytes' => $this->requestSizeBytes,
                'response_size_bytes' => $this->responseSizeBytes,
                'authentication_method' => $this->authenticationMethod,
                'api_version' => $this->apiVersion,
                'correlation_id' => $this->correlationId,
                // Format JSON bodies with pretty printing for better readability in OpenSearch Dashboards
                'request_body' => $this->formatJsonIfValid($this->requestBody),
                'response_body' => $this->formatJsonIfValid($this->responseBody),
                // Format headers arrays as pretty-printed JSON
                'request_headers' => $this->formatArrayAsJson($this->requestHeaders),
                'response_headers' => $this->formatArrayAsJson($this->responseHeaders),
            ], fn ($value) => $value !== null)
        );
    }
}
