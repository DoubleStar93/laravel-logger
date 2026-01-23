<?php

namespace Ermetix\LaravelLogger\Logging\Contracts;

use Monolog\LogRecord;

interface OpenSearchDocumentBuilder
{
    /**
     * Decide which index to write to for this record.
     */
    public function index(LogRecord $record): string;

    /**
     * Build the document that will be indexed in OpenSearch (the _source).
     *
     * @return array<string, mixed>
     */
    public function document(LogRecord $record): array;
}
