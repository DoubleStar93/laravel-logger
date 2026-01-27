# OpenSearch Dashboards - Campi Default Ottimizzati

Questo documento elenca i campi ottimizzati da mostrare di default quando si apre un index pattern in OpenSearch Dashboards.

## Come configurare i campi default

In OpenSearch Dashboards:
1. Vai su **Management** → **Index Patterns**
2. Seleziona o crea l'index pattern
3. Clicca su **Edit** (icona matita)
4. Nella sezione **Fields**, seleziona i campi da mostrare di default
5. Clicca su **Save**

Oppure usa l'API OpenSearch per configurare i campi default programmaticamente.

---

## api_log

**Campi essenziali (mostrare di default):**

1. `@timestamp` - Data/ora evento
2. `method` - Metodo HTTP (GET, POST, etc.)
3. `path` - Percorso URL
4. `route_name` - Nome route Laravel
5. `status` - Status code HTTP
6. `duration_ms` - Durata richiesta (ms)
7. `user_id` - ID utente autenticato
8. `ip` - IP client
9. `request_id` - ID correlazione

**Campi opzionali (utili ma non essenziali):**
- `level` - Livello log
- `environment` - Ambiente (local, staging, production)
- `user_agent` - User agent
- `referer` - Referrer URL
- `response_size_bytes` - Dimensione risposta

**Campi nascosti (non mostrare di default):**
- `request_body` - Può essere molto grande
- `response_body` - Può essere molto grande
- `request_headers` - Può essere molto grande
- `response_headers` - Può essere molto grande
- `query_string` - Può essere molto grande
- Campi di correlazione avanzata (`trace_id`, `span_id`, `parent_request_id`) - Solo se necessario

---

## general_log

**Campi essenziali (mostrare di default):**

1. `@timestamp` - Data/ora evento
2. `message` - Messaggio descrittivo (utile per general_log)
3. `event` - Nome evento
4. `entity_type` - Tipo entità (es. "user", "post")
5. `entity_id` - ID entità
6. `action_type` - Tipo azione
7. `user_id` - ID utente
8. `level` - Livello log
9. `request_id` - ID correlazione

**Campi opzionali (utili per debug):**
- `file` - File sorgente
- `line` - Riga sorgente
- `class` - Classe sorgente
- `function` - Funzione sorgente
- `feature` - Feature/modulo
- `environment` - Ambiente

**Campi nascosti (non mostrare di default):**
- Campi di correlazione avanzata (`trace_id`, `span_id`, `parent_request_id`) - Solo se necessario

---

## orm_log

**Campi essenziali (mostrare di default):**

1. `@timestamp` - Data/ora evento
2. `model` - Nome modello Eloquent
3. `action` - Azione (create, update, delete, query)
4. `query_type` - Tipo query SQL (SELECT, INSERT, UPDATE, DELETE)
5. `table` - Nome tabella
6. `duration_ms` - Durata query (ms)
7. `is_slow_query` - Query lenta (true/false)
8. `user_id` - ID utente
9. `request_id` - ID correlazione

**Campi opzionali (utili per debug):**
- `model_id` - ID modello
- `connection` - Nome connessione DB
- `transaction_id` - ID transazione
- `query` - Query SQL completa (può essere lunga)
- `bindings` - Bindings query (può essere lungo)

**Campi nascosti (non mostrare di default):**
- `previous_value` - Valori precedenti (oggetto, può essere grande)
- `after_value` - Valori dopo modifica (oggetto, può essere grande)
- Campi di correlazione avanzata (`trace_id`, `span_id`, `parent_request_id`) - Solo se necessario

---

## integration_log

**Campi essenziali (mostrare di default):**

1. `@timestamp` - Data/ora evento
2. `integration_name` - Nome integrazione (es. "stripe", "payment_gateway")
3. `url` - URL chiamata
4. `method` - Metodo HTTP
5. `status` - Status code HTTP
6. `duration_ms` - Durata chiamata (ms)
7. `level` - Livello log
8. `request_id` - ID correlazione

**Campi opzionali (utili per debug):**
- `external_id` - ID esterno (es. transaction_id)
- `correlation_id` - ID correlazione esterna
- `attempt` - Numero tentativo
- `max_attempts` - Numero massimo tentativi
- `error_message` - Messaggio errore (se presente)

**Campi nascosti (non mostrare di default):**
- `request_body` - Può essere molto grande
- `response_body` - Può essere molto grande
- `headers` - Può essere molto grande
- `request_size_bytes` - Dettaglio tecnico
- `response_size_bytes` - Dettaglio tecnico
- Campi di correlazione avanzata (`trace_id`, `span_id`, `parent_request_id`) - Solo se necessario

---

## job_log

**Campi essenziali (mostrare di default):**

1. `@timestamp` - Data/ora evento
2. `job` - Nome job/command
3. `command` - Comando eseguito
4. `status` - Status (success, failed, etc.)
5. `duration_ms` - Durata esecuzione (ms)
6. `exit_code` - Exit code
7. `frequency` - Frequenza di esecuzione (per cron jobs)
8. `output` - Output del job
9. `level` - Livello log
10. `request_id` - ID correlazione

**Campi opzionali (utili per monitoraggio):**
- `job_id` - ID job
- `queue_name` - Nome coda
- `attempts` - Numero tentativi
- `max_attempts` - Numero massimo tentativi
- `memory_peak_mb` - Picco memoria (MB)
- `environment` - Ambiente

**Campi nascosti (non mostrare di default):**
- Campi di correlazione avanzata (`trace_id`, `span_id`, `parent_request_id`) - Solo se necessario

---

## error_log

**Campi essenziali (mostrare di default):**

1. `@timestamp` - Data/ora evento
2. `exception_class` - Classe eccezione
3. `code` - Codice errore
4. `level` - Livello log (error, critical)
5. `context_route` - Route dove si è verificato l'errore
6. `context_method` - Metodo HTTP
7. `context_url` - URL completa
8. `context_user_id` - ID utente
9. `request_id` - ID correlazione

**Campi opzionali (utili per debug approfondito):**
- `stack_trace` - Stack trace completo (può essere molto lungo)
- `previous_exception` - Eccezione precedente (se presente)
- `environment` - Ambiente

**Campi nascosti (non mostrare di default):**
- Campi di correlazione avanzata (`trace_id`, `span_id`, `parent_request_id`) - Solo se necessario

---

## Campi comuni a tutti gli indici

**Sempre utili:**
- `@timestamp` - **SEMPRE** mostrare (ordinamento temporale)
- `request_id` - **SEMPRE** mostrare (correlazione)
- `level` - Utile per filtri
- `environment` - Utile per filtri multi-ambiente

**Opzionali (mostrare solo se necessario):**
- `hostname` - Solo per cluster multi-server
- `service_name` - Solo per microservizi
- `app_version` - Solo se importante tracciare versioni
- `session_id` - Solo per correlazione sessioni utente
- `tags` - Solo se usati per categorizzazione

**Nascosti (non mostrare di default):**
- `trace_id`, `span_id`, `parent_request_id` - Solo per distributed tracing avanzato

---

## Riepilogo ottimizzazioni applicate

### Campi rimossi (ridondanti):
- ❌ `message` - Rimosso da tutti tranne `general_log` (campi strutturati più utili)
- ❌ `file`, `line`, `class`, `function` - Rimossi da `api_log`, `orm_log`, `integration_log`, `job_log`, `error_log`
  - ✅ Mantenuti solo in `general_log` (utili per debug eventi generici)
  - ✅ `error_log` ha `stack_trace` che contiene tutto

### Campi mantenuti:
- ✅ `message` - Solo in `general_log` (utile per descrizioni umane)
- ✅ `file`, `line`, `class`, `function` - Solo in `general_log` (utili per debug)
- ✅ Tutti i campi strutturati specifici per ogni tipo di log

---

## Esempio configurazione programmatica

Per configurare i campi default via API OpenSearch:

```bash
# Esempio per api_log
PUT /_index_template/api_log-template
{
  "index_patterns": ["api_log*"],
  "template": {
    "settings": {
      "index": {
        "default_fields": [
          "@timestamp",
          "method",
          "path",
          "route_name",
          "status",
          "duration_ms",
          "user_id",
          "ip",
          "request_id"
        ]
      }
    }
  }
}
```

**Nota:** OpenSearch Dashboards gestisce i campi default a livello di UI, non tramite template. I campi default devono essere configurati manualmente nell'interfaccia o tramite API Dashboards.
