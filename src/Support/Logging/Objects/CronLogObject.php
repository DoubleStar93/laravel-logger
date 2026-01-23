<?php

namespace Ermetix\LaravelLogger\Support\Logging\Objects;

class CronLogObject extends BaseLogObject
{
    public function __construct(
        string $message,
        public readonly ?string $job = null,
        public readonly ?string $jobId = null,
        public readonly ?string $queueName = null,
        public readonly ?int $attempts = null,
        public readonly ?int $maxAttempts = null,
        public readonly ?string $command = null,
        public readonly ?string $status = null,
        public readonly ?int $durationMs = null,
        public readonly ?int $exitCode = null,
        public readonly ?float $memoryPeakMb = null,
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
        return 'cron_log';
    }

    public function toArray(): array
    {
        // cron_log doesn't need source location fields (file/line/class/function)
        // as they would always point to the point where Log::cron() is called,
        // not the actual job class (which is already in the 'job' field)
        return array_merge(
            $this->getCommonFields(includeSourceLocation: false),
            array_filter([
                'job' => $this->job,
                'job_id' => $this->jobId,
                'queue_name' => $this->queueName,
                'attempts' => $this->attempts,
                'max_attempts' => $this->maxAttempts,
                'command' => $this->command,
                'status' => $this->status,
                'duration_ms' => $this->durationMs,
                'exit_code' => $this->exitCode,
                'memory_peak_mb' => $this->memoryPeakMb,
            ], fn ($value) => $value !== null)
        );
    }
}
