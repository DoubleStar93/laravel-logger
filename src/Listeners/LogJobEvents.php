<?php

namespace Ermetix\LaravelLogger\Listeners;

use Ermetix\LaravelLogger\Facades\LaravelLogger;
use Ermetix\LaravelLogger\Support\Logging\Objects\JobLogObject;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Context;

/**
 * Automatically log job execution events to job_log index.
 * 
 * Tracks job start time, duration, status, and other metadata.
 */
class LogJobEvents
{
    /**
     * Track job start times to calculate duration.
     * 
     * @var array<string, float>
     */
    private array $jobStartTimes = [];

    /**
     * Track captured output for each job.
     * 
     * @var array<string, string>
     */
    private array $jobOutputs = [];

    /**
     * Track output buffer levels for each job.
     * 
     * @var array<string, int>
     */
    private array $jobOutputBufferLevels = [];

    /**
     * Handle job processing event to track start time and start output capture.
     */
    public function handleJobProcessing(JobProcessing $event): void
    {
        $jobId = $this->getJobId($event->job);
        $this->jobStartTimes[$jobId] = microtime(true);
        
        // Start output buffering to capture any output from the job
        // Store the current buffer level so we can restore it later
        $this->jobOutputBufferLevels[$jobId] = ob_get_level();
        ob_start();
    }

    /**
     * Handle job processed event (success).
     */
    public function handleJobProcessed(JobProcessed $event): void
    {
        $jobId = $this->getJobId($event->job);
        
        // Capture output from output buffering
        $this->captureJobOutput($jobId);
        
        $this->logJob($event->job, 'success', null);
    }

    /**
     * Handle job failed event.
     */
    public function handleJobFailed(JobFailed $event): void
    {
        $jobId = $this->getJobId($event->job);
        
        // Capture output from output buffering (even if job failed)
        $this->captureJobOutput($jobId);
        
        $exception = $event->exception;
        $errorMessage = $exception ? $exception->getMessage() : 'Job failed';
        
        $this->logJob($event->job, 'failed', $errorMessage, $exception?->getCode() ?? 1);
    }

    /**
     * Capture output from output buffering for a job.
     */
    private function captureJobOutput(string $jobId): void
    {
        // Get the buffer level we started with
        $startLevel = $this->jobOutputBufferLevels[$jobId] ?? 0;
        $currentLevel = ob_get_level();
        
        // Only capture if we have a buffer we started
        if ($currentLevel > $startLevel) {
            $output = ob_get_clean();
            if ($output !== false && $output !== '') {
                $this->jobOutputs[$jobId] = $output;
            }
        }
        
        // Clean up
        unset($this->jobOutputBufferLevels[$jobId]);
    }

    /**
     * Log job execution details.
     */
    private function logJob(Job $job, string $status, ?string $errorMessage = null, int $exitCode = 0): void
    {
        $jobId = $this->getJobId($job);
        $startTime = $this->jobStartTimes[$jobId] ?? null;
        
        // Calculate duration
        $durationMs = $startTime !== null 
            ? (int) round((microtime(true) - $startTime) * 1000)
            : null;

        // Clean up start time
        unset($this->jobStartTimes[$jobId]);
        
        // Get captured output (already captured in handleJobProcessed/handleJobFailed)
        $capturedOutput = $this->jobOutputs[$jobId] ?? null;
        unset($this->jobOutputs[$jobId]);

        // Get job name
        $jobName = $this->getJobName($job);
        
        // Determine if it's a cron job (scheduled command) and get frequency
        $isCron = $this->isCronJob($job);
        $frequency = $isCron ? $this->getFrequency($job) : null;
        
        // Get command if it's a scheduled command
        $command = $isCron ? $this->getCommand($job) : null;
        
        // Get queue name
        $queueName = $job->getQueue();
        
        // Get attempts
        $attempts = $job->attempts();
        $maxAttempts = null;
        if (method_exists($job, 'maxTries')) {
            try {
                $maxAttempts = $job->maxTries();
            } catch (\Throwable $e) {
                // Ignore if method doesn't work
            }
        }
        
        // Get job ID (UUID if available)
        $jobUuid = null;
        if (method_exists($job, 'uuid')) {
            $jobUuid = $job->uuid();
        }
        
        // Get memory peak
        $memoryPeakMb = round(memory_get_peak_usage(true) / 1024 / 1024, 2);

        // Build output message - try multiple sources in order:
        // 1. Error message (if failed)
        // 2. Captured output from output buffering (automatic)
        // 3. Default success/failure message
        $output = $errorMessage;
        
        if ($output === null) {
            // First, try captured output from output buffering (automatic)
            if (isset($capturedOutput) && $capturedOutput !== '') {
                $output = $capturedOutput;
            } else {
                // Fallback to default message
                if ($status === 'success') {
                    $output = 'Job completed successfully';
                } else {
                    $output = 'Job failed';
                }
            }
        }

        // Get request_id from context
        $requestId = Context::get('request_id');
        $parentRequestId = Context::get('parent_request_id');

        // Determine log level based on status
        $level = $status === 'failed' ? 'error' : 'info';

        try {
            LaravelLogger::job(new JobLogObject(
                message: $status === 'success' 
                    ? "job_completed: {$jobName}"
                    : "job_failed: {$jobName}",
                job: $jobName,
                jobId: $jobUuid,
                queueName: $queueName,
                attempts: $attempts,
                maxAttempts: $maxAttempts,
                command: $command,
                status: $status,
                durationMs: $durationMs,
                exitCode: $status === 'failed' ? $exitCode : 0,
                memoryPeakMb: $memoryPeakMb,
                frequency: $frequency,
                output: $output,
                level: $level,
                parentRequestId: $parentRequestId,
            ), defer: false); // Log immediately, not deferred
        } catch (\Throwable $e) {
            // Silently fail to avoid breaking job execution
            // Log to standard Laravel log if available
            if (function_exists('logger')) {
                logger()->error('Failed to log job event', [
                    'error' => $e->getMessage(),
                    'job' => $jobName,
                ]);
            }
        }
    }

    /**
     * Get unique job ID for tracking.
     */
    private function getJobId(Job $job): string
    {
        // Try to get UUID first
        if (method_exists($job, 'uuid')) {
            $uuid = $job->uuid();
            if ($uuid !== null) {
                return (string) $uuid;
            }
        }
        
        // Fallback to job ID
        return (string) $job->getJobId();
    }

    /**
     * Get job name/class.
     */
    private function getJobName(Job $job): string
    {
        // For queued jobs, get the class name
        $payload = $job->payload();
        
        if (isset($payload['displayName'])) {
            return $payload['displayName'];
        }
        
        if (isset($payload['job'])) {
            return $payload['job'];
        }
        
        // For commands, get command signature
        $command = $job->resolveName();
        if ($command && class_exists($command)) {
            $instance = unserialize($payload['data']['command'] ?? '');
            if ($instance instanceof Command) {
                return $instance->getName() ?? get_class($instance);
            }
            return $command;
        }
        
        return get_class($job);
    }

    /**
     * Determine if job is a scheduled command (cron job).
     */
    private function isCronJob(Job $job): bool
    {
        $payload = $job->payload();
        
        // Check if it's a scheduled command
        if (isset($payload['data']['command'])) {
            try {
                $command = unserialize($payload['data']['command']);
                if ($command instanceof Command) {
                    return true;
                }
            } catch (\Throwable $e) {
                // Ignore unserialize errors
            }
        }
        
        // Check job class name for command patterns
        $jobName = $this->getJobName($job);
        if (str_contains($jobName, 'Illuminate\\Console\\') || 
            str_contains($jobName, 'Command')) {
            return true;
        }
        
        return false;
    }

    /**
     * Get command string for scheduled commands.
     */
    private function getCommand(Job $job): ?string
    {
        $payload = $job->payload();
        
        if (isset($payload['data']['command'])) {
            try {
                $command = unserialize($payload['data']['command']);
                if ($command instanceof Command) {
                    return 'php artisan ' . ($command->getName() ?? '');
                }
            } catch (\Throwable $e) {
                // Ignore unserialize errors
            }
        }
        
        return null;
    }

    /**
     * Get frequency for scheduled commands (cron expression or description).
     */
    private function getFrequency(Job $job): ?string
    {
        $payload = $job->payload();
        
        // Try to get frequency from payload if available
        if (isset($payload['data']['frequency'])) {
            return $payload['data']['frequency'];
        }
        
        // Try to get cron expression from payload
        if (isset($payload['data']['cron'])) {
            return $payload['data']['cron'];
        }
        
        // Try to get schedule expression from payload
        if (isset($payload['data']['schedule'])) {
            return $payload['data']['schedule'];
        }
        
        // For scheduled commands, try to extract from command instance
        if (isset($payload['data']['command'])) {
            try {
                $command = unserialize($payload['data']['command']);
                if ($command instanceof Command) {
                    // Check if command has a schedule method or property
                    // This is a best-effort approach since Laravel doesn't expose
                    // the schedule directly in the job payload
                    // Users can manually set frequency when logging
                    return null;
                }
            } catch (\Throwable $e) {
                // Ignore unserialize errors
            }
        }
        
        return null;
    }

}
