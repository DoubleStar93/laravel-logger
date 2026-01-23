<?php

namespace Ermetix\LaravelLogger\Support\Logging\Objects;

use Ermetix\LaravelLogger\Support\Logging\Contracts\LogObject;

abstract class BaseLogObject implements LogObject
{
    public function __construct(
        protected string $message,
        protected string $level = 'info',
        // Common correlation fields
        public readonly ?string $parentRequestId = null,
        public readonly ?string $traceId = null,
        public readonly ?string $spanId = null,
        public readonly ?string $sessionId = null,
        // Environment/Context fields
        public readonly ?string $environment = null,
        public readonly ?string $hostname = null,
        public readonly ?string $serviceName = null,
        public readonly ?string $appVersion = null,
        // Source location fields
        public readonly ?string $file = null,
        public readonly ?int $line = null,
        public readonly ?string $class = null,
        public readonly ?string $function = null,
        // Categorization
        public readonly ?array $tags = null,
    ) {}

    public function level(): string
    {
        return $this->level;
    }

    public function message(): string
    {
        return $this->message;
    }

    /**
     * Get common fields that should be included in all log objects.
     * 
     * @param bool $includeSourceLocation Whether to include file/line/class/function fields.
     *                                    These are useful for debugging but not needed for all log types
     *                                    (e.g., api_log doesn't need them as they're always from middleware).
     * @param bool $includeMessage Whether to include message field.
     *                              Only useful for general_log where human-readable descriptions are valuable.
     *                              Other log types have structured fields that make message redundant.
     *
     * @return array<string, mixed>
     */
    protected function getCommonFields(bool $includeSourceLocation = true, bool $includeMessage = false): array
    {
        $fields = [
            'level' => $this->level,
            'parent_request_id' => $this->parentRequestId,
            'trace_id' => $this->traceId,
            'span_id' => $this->spanId,
            'session_id' => $this->sessionId,
            'environment' => $this->environment,
            'hostname' => $this->hostname,
            'service_name' => $this->serviceName,
            'app_version' => $this->appVersion,
            'tags' => $this->tags,
        ];

        // Only include message field if requested (only for general_log)
        if ($includeMessage) {
            $fields['message'] = $this->message;
        }

        // Only include source location fields if requested
        if ($includeSourceLocation) {
            $fields['file'] = $this->file;
            $fields['line'] = $this->line;
            $fields['class'] = $this->class;
            $fields['function'] = $this->function;
        }

        return array_filter($fields, fn ($value) => $value !== null);
    }

    /**
     * Format JSON string with pretty printing if valid JSON, otherwise return as-is.
     * Useful for request_body, response_body, and headers fields.
     *
     * @param string|null $jsonString The JSON string to format
     * @return string|null Formatted JSON or original string if not valid JSON
     */
    protected function formatJsonIfValid(?string $jsonString): ?string
    {
        if ($jsonString === null || $jsonString === '') {
            return null;
        }
        
        // Try to decode and re-encode with pretty print
        $decoded = json_decode($jsonString, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            // Valid JSON, format it with pretty print
            return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        
        // Not valid JSON, return as-is
        return $jsonString;
    }

    /**
     * Format array as pretty-printed JSON string.
     * Useful for headers fields.
     *
     * @param array|null $data The array to format
     * @return string|null Formatted JSON string or null
     */
    protected function formatArrayAsJson(?array $data): ?string
    {
        if ($data === null || empty($data)) {
            return null;
        }
        
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Convenience getters for public properties (e.g. $log->method()).
     *
     * @return mixed
     */
    public function __call(string $name, array $arguments)
    {
        if (property_exists($this, $name)) {
            return $this->{$name};
        }

        throw new \BadMethodCallException(sprintf('Method %s::%s() does not exist.', static::class, $name));
    }

    /**
     * Subclasses must implement this to return their specific fields.
     * They should merge their specific fields with getCommonFields().
     *
     * @return array<string, mixed>
     */
    abstract public function toArray(): array;
}
