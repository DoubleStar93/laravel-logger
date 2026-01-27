<?php

namespace Ermetix\LaravelLogger\Support\Logging;

use Ermetix\LaravelLogger\Support\Logging\Objects\ApiLogObject;
use Ermetix\LaravelLogger\Support\Logging\Objects\JobLogObject;
use Ermetix\LaravelLogger\Support\Logging\Objects\ErrorLogObject;
use Ermetix\LaravelLogger\Support\Logging\Objects\GeneralLogObject;
use Ermetix\LaravelLogger\Support\Logging\Objects\IntegrationLogObject;
use Ermetix\LaravelLogger\Support\Logging\Objects\OrmLogObject;

/**
 * Type-safe logger that enforces specific LogObject types for each log category.
 * 
 * Each method requires the corresponding LogObject type, ensuring type safety at compile time.
 */
class TypedLogger
{
    public function __construct(
        private readonly MultiChannelLogger $multi,
    ) {}

    /**
     * Log a general application event.
     * 
     * @param GeneralLogObject $object Required. The general log object to log.
     * @param bool $defer If true, logs are accumulated in memory and written at the end of the request/job.
     *                    If false, logs are written immediately (sync).
     * @return void
     */
    public function general(GeneralLogObject $object, bool $defer = true): void
    {
        $this->multi->log($object, $defer);
    }

    /**
     * Log an API request/response event.
     * 
     * @param ApiLogObject $object Required. The API log object to log.
     * @param bool $defer If true, logs are accumulated in memory and written at the end of the request/job.
     *                    If false, logs are written immediately (sync).
     * @return void
     */
    public function api(ApiLogObject $object, bool $defer = true): void
    {
        $this->multi->log($object, $defer);
    }

    /**
     * Log a job or scheduled task event.
     * 
     * @param JobLogObject $object Required. The job log object to log.
     * @param bool $defer If true, logs are accumulated in memory and written at the end of the request/job.
     *                    If false, logs are written immediately (sync).
     * @return void
     */
    public function job(JobLogObject $object, bool $defer = true): void
    {
        $this->multi->log($object, $defer);
    }

    /**
     * Log a cron job or scheduled task event.
     * 
     * @param JobLogObject $object Required. The job log object to log.
     * @param bool $defer If true, logs are accumulated in memory and written at the end of the request/job.
     *                    If false, logs are written immediately (sync).
     * @return void
     * @deprecated Use job() instead. This method is kept for backward compatibility.
     */
    public function cron(JobLogObject $object, bool $defer = true): void
    {
        $this->multi->log($object, $defer);
    }

    /**
     * Log an external integration call event.
     * 
     * @param IntegrationLogObject $object Required. The integration log object to log.
     * @param bool $defer If true, logs are accumulated in memory and written at the end of the request/job.
     *                    If false, logs are written immediately (sync).
     * @return void
     */
    public function integration(IntegrationLogObject $object, bool $defer = true): void
    {
        $this->multi->log($object, $defer);
    }

    /**
     * Log an ORM/database operation event.
     * 
     * @param OrmLogObject $object Required. The ORM log object to log.
     * @param bool $defer If true, logs are accumulated in memory and written at the end of the request/job.
     *                    If false, logs are written immediately (sync).
     * @return void
     */
    public function orm(OrmLogObject $object, bool $defer = true): void
    {
        $this->multi->log($object, $defer);
    }

    /**
     * Log an error/exception event.
     * 
     * @param ErrorLogObject $object Required. The error log object to log.
     * @param bool $defer If true, logs are accumulated in memory and written at the end of the request/job.
     *                    If false, logs are written immediately (sync).
     * @return void
     */
    public function error(ErrorLogObject $object, bool $defer = true): void
    {
        $this->multi->log($object, $defer);
    }
}
