<?php

namespace Ermetix\LaravelLogger\Support\Logging\Contracts;

interface LogObject
{
    /**
     * One of: api_log, general_log, cron_log, integration_log, orm_log, error_log
     */
    public function index(): string;

    /**
     * Monolog/Laravel level name, e.g. info, warning, error, debug...
     */
    public function level(): string;

    /**
     * The message/event name.
     */
    public function message(): string;

    /**
     * Get all specific fields for this log type as a flat array.
     * These fields will be placed directly in the document (not inside a 'context' object).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
