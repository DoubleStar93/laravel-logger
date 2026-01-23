# Configurare Colonne di Default in OpenSearch Dashboards Discover

## Limitazione

OpenSearch Dashboards **non permette** di impostare direttamente le colonne visibili nella tabella Discover tramite API. Questo è un limite della piattaforma.

## Soluzione: Configurazione Manuale

Dopo aver eseguito lo script di setup, devi configurare manualmente le colonne visibili per ogni index pattern.

### Passi per Configurare le Colonne di Default

1. **Apri OpenSearch Dashboards**: http://localhost:5601

2. **Vai su Discover**: Clicca su "Discover" nel menu laterale

3. **Seleziona l'Index Pattern**: 
   - Clicca sul dropdown in alto a sinistra
   - Seleziona l'index pattern desiderato (es. `api_log*`)

4. **Aggiungi Colonne**:
   - Nella colonna laterale sinistra, vedrai "Available fields"
   - Clicca sul pulsante "+" accanto a ciascun campo che vuoi mostrare nella tabella
   - I campi verranno aggiunti come colonne nella tabella

5. **Ordina le Colonne**:
   - Trascina le colonne per riordinarle
   - Clicca sulla "X" per rimuovere colonne non necessarie

6. **Salva la Vista** (Opzionale):
   - Clicca su "Save" in alto a destra
   - Dai un nome alla vista salvata (es. "API Logs - Default View")
   - Ora puoi ripristinare questa vista in qualsiasi momento

## Campi Consigliati per Ogni Index Pattern

### api_log*
- `@timestamp`
- `method`
- `path`
- `route_name`
- `status`
- `duration_ms`
- `user_id`
- `ip`
- `request_id`

### general_log*
- `@timestamp`
- `message`
- `event`
- `entity_type`
- `entity_id`
- `action_type`
- `user_id`
- `level`
- `request_id`

### cron_log*
- `@timestamp`
- `job`
- `command`
- `status`
- `duration_ms`
- `exit_code`
- `level`
- `request_id`

### integration_log*
- `@timestamp`
- `integration_name`
- `url`
- `method`
- `status`
- `duration_ms`
- `level`
- `request_id`

### orm_log*
- `@timestamp`
- `model`
- `action`
- `query_type`
- `table`
- `duration_ms`
- `is_slow_query`
- `user_id`
- `request_id`

### error_log*
- `@timestamp`
- `exception_class`
- `code`
- `level`
- `context_route`
- `context_method`
- `context_url`
- `context_user_id`
- `request_id`

## Nota

I documenti di test creati durante lo setup contengono tutti questi campi, quindi saranno disponibili per la selezione in OpenSearch Dashboards.
