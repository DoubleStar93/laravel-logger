# OpenSearch index schema (ORM-style)

This document describes the OpenSearch mappings for each log index.

**Single source of truth** for mappings is the JSON templates in:

- `resources/opensearch/index-templates/*.json`

This file is a human-readable summary derived from those templates.

## Common fields (present in all indices)

| Field | Type | Notes |
|---|---:|---|
| `@timestamp` | `date` | event timestamp |
| `level` | `keyword` | `info`, `warning`, `error`, ... |
| `message` | `text` | also has `message.keyword` |
| `request_id` | `keyword` | correlation id |
| `parent_request_id` | `keyword` | parent correlation |
| `trace_id` | `keyword` | tracing |
| `span_id` | `keyword` | tracing |
| `session_id` | `keyword` | session correlation |
| `environment` | `keyword` | env name |
| `hostname` | `keyword` | host |
| `service_name` | `keyword` | app/service |
| `app_version` | `keyword` | version |
| `file` | `keyword` | source file (when available) |
| `line` | `integer` | source line (when available) |
| `class` | `keyword` | source class (when available) |
| `function` | `keyword` | source function (when available) |
| `tags` | `keyword` | categorization |

All templates use `dynamic: false` at root level, unless explicitly stated otherwise for a sub-object (see `integration_log.headers`, `orm_log.previous_value`, `orm_log.after_value`).

## api_log

Template: `resources/opensearch/index-templates/api_log-template.json`

| Field | Type | Notes |
|---|---:|---|
| `method` | `keyword` | HTTP method |
| `path` | `keyword` | URL path |
| `route_name` | `keyword` | Laravel route name |
| `status` | `integer` | HTTP status |
| `duration_ms` | `integer` | duration in ms |
| `ip` | `ip` | client ip |
| `user_id` | `keyword` | authenticated user |
| `user_agent` | `text` | user agent |
| `referer` | `keyword` | referer |
| `query_string` | `text` | `index: false` |
| `request_size_bytes` | `integer` | size |
| `response_size_bytes` | `integer` | size |
| `authentication_method` | `keyword` | auth type |
| `api_version` | `keyword` | api version |
| `correlation_id` | `keyword` | correlation |
| `request_body` | `text` | `index: false` |
| `response_body` | `text` | `index: false` |
| `request_headers` | `text` | `index: false` |
| `response_headers` | `text` | `index: false` |

## general_log

Template: `resources/opensearch/index-templates/general_log-template.json`

| Field | Type | Notes |
|---|---:|---|
| `event` | `keyword` | event name |
| `user_id` | `keyword` | user |
| `entity_type` | `keyword` | entity type |
| `entity_id` | `keyword` | entity id |
| `feature` | `keyword` | feature area |
| `action_type` | `keyword` | action |

## cron_log

Template: `resources/opensearch/index-templates/cron_log-template.json`

| Field | Type | Notes |
|---|---:|---|
| `job` | `keyword` | job name |
| `job_id` | `keyword` | job id |
| `queue_name` | `keyword` | queue |
| `attempts` | `integer` | current attempt |
| `max_attempts` | `integer` | max attempts |
| `command` | `keyword` | artisan command |
| `status` | `keyword` | status |
| `duration_ms` | `integer` | duration |
| `exit_code` | `integer` | exit |
| `memory_peak_mb` | `float` | memory peak |

## integration_log

Template: `resources/opensearch/index-templates/integration_log-template.json`

| Field | Type | Notes |
|---|---:|---|
| `integration_name` | `keyword` | integration |
| `url` | `keyword` | url |
| `method` | `keyword` | method |
| `status` | `integer` | status |
| `duration_ms` | `integer` | duration |
| `external_id` | `keyword` | external id |
| `correlation_id` | `keyword` | correlation |
| `attempt` | `integer` | attempt |
| `max_attempts` | `integer` | max attempts |
| `request_size_bytes` | `integer` | size |
| `response_size_bytes` | `integer` | size |
| `request_body` | `text` | indexed |
| `response_body` | `text` | indexed |
| `headers` | `object` | `dynamic: true` |
| `error_message` | `text` | error |

## orm_log

Template: `resources/opensearch/index-templates/orm_log-template.json`

| Field | Type | Notes |
|---|---:|---|
| `model` | `keyword` | model FQCN |
| `model_id` | `keyword` | model id |
| `action` | `keyword` | create/update/delete/read |
| `query` | `text` | sql |
| `query_type` | `keyword` | SELECT/INSERT/... |
| `is_slow_query` | `boolean` | slow flag |
| `duration_ms` | `integer` | duration |
| `bindings` | `text` | bindings |
| `connection` | `keyword` | connection |
| `table` | `keyword` | table |
| `transaction_id` | `keyword` | transaction |
| `user_id` | `keyword` | user |
| `previous_value` | `object` | `dynamic: true` |
| `after_value` | `object` | `dynamic: true` |

## error_log

Template: `resources/opensearch/index-templates/error_log-template.json`

| Field | Type | Notes |
|---|---:|---|
| `stack_trace` | `text` | `index: false` |
| `exception_class` | `keyword` | exception class |
| `file` | `keyword` | file |
| `line` | `integer` | line |
| `code` | `integer` | code |
| `previous_exception` | `object` | `{class: keyword, message: text}` |
| `context_user_id` | `keyword` | request user id (if available) |
| `context_route` | `keyword` | request route name (if available) |
| `context_method` | `keyword` | request HTTP method (if available) |
| `context_url` | `keyword` | request URL (if available) |

