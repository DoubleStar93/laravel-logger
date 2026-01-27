## OpenSearch logging design (api_log / general_log / job_log / integration_log / orm_log)

Here we model your "collections" as **real OpenSearch indices**:

- `api_log` (API access + API domain logs)
- `general_log` (generic application events)
- `job_log` (jobs / scheduled tasks / cron / background tasks)
- `integration_log` (calls to external integrations)
- `orm_log` (ORM/Eloquent operations: queries, model changes)

All are correlated via **`request_id`**.

### Document schema (common)

The OpenSearch templates in this package map fields **at root level** (strict mapping, `dynamic: false`).

For the full index schema (fields + types), see the single source of truth:

- `docs/opensearch-index-schema.md`

### Index templates (mappings)

Provided as separate JSON templates (one per index) with **strict mapping** (dynamic: false):

- `opensearch/index-templates/api_log-template.json`
- `opensearch/index-templates/general_log-template.json`
- `opensearch/index-templates/job_log-template.json`
- `opensearch/index-templates/integration_log-template.json`
- `opensearch/index-templates/orm_log-template.json`

Each template defines index-specific fields (at root level) based on the index type.

### Retention

OpenSearch uses **Index State Management (ISM)**.

Provided example policy (delete after 30 days):

- `opensearch/ism/logs-retention-policy.json`

### Laravel channels

Configured channels (see `config/logging.php`):

- `opensearch` → indexes into `api_log`, `general_log`, `job_log`, `integration_log`, `orm_log`

Index routing is controlled by the log context field `log_index` (or via `extra.log_index`).

### Index-specific fields
To avoid duplicating field lists across docs, refer to:

- `docs/opensearch-index-schema.md`

### Setup commands (examples)

Create templates (apply all 5):

```bash
PUT _index_template/api_log-template
PUT _index_template/general_log-template
PUT _index_template/job_log-template
PUT _index_template/integration_log-template
PUT _index_template/orm_log-template
```

Or use the automated setup scripts:
- `php docker/opensearch/setup.php` (cross-platform, funziona su Windows, Linux e macOS)

Create ISM policy:

`PUT _plugins/_ism/policies/logs-retention-policy` with `opensearch/ism/logs-retention-policy.json`

### Query examples

All logs for a request:

`GET api_log/_search` with query:

- filter `request_id.keyword = "..."`

Or across all indices:

`GET api_log,general_log,job_log,integration_log,orm_log/_search`

Query ORM operations for a specific model:

`GET orm_log/_search` with query:

- filter `context.model.keyword = "App\Models\User"`
- filter `context.action.keyword = "update"`

