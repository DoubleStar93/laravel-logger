# Diagramma Indici OpenSearch

Questo diagramma illustra la struttura degli indici OpenSearch e come vengono utilizzati.

```mermaid
flowchart TB
  Request[Request_or_Job] --> Source[Middleware_or_Code]
  Source --> Type{Log_type}

  Type --> Api[ApiLogObject]
  Type --> General[GeneralLogObject]
  Type --> Cron[CronLogObject]
  Type --> Integration[IntegrationLogObject]
  Type --> Orm[OrmLogObject]
  Type --> Err[ErrorLogObject]

  Api -->|"log_index=api_log"| OS_api[(api_log)]
  General -->|"log_index=general_log"| OS_general[(general_log)]
  Cron -->|"log_index=cron_log"| OS_cron[(cron_log)]
  Integration -->|"log_index=integration_log"| OS_integration[(integration_log)]
  Orm -->|"log_index=orm_log"| OS_orm[(orm_log)]
  Err -->|"log_index=error_log"| OS_error[(error_log)]

  Templates[OpenSearch_index_templates] -.-> OS_api
  Templates -.-> OS_general
  Templates -.-> OS_cron
  Templates -.-> OS_integration
  Templates -.-> OS_orm
  Templates -.-> OS_error
```

## Schema (ORM) degli indici

Lo schema completo dei campi e dei tipi per ciascun indice è mantenuto in un singolo file per evitare duplicazioni:

- `docs/opensearch-index-schema.md`
