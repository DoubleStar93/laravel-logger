# Proposta campi aggiuntivi per debugging e ricostruzione bug

## Campi comuni (tutti gli indici)

Questi campi dovrebbero essere disponibili in tutti i template per correlazione e debugging:

### Exception/Error tracking
- `exception_type` (keyword) - Classe dell'eccezione (es. `Illuminate\Database\QueryException`)
- `exception_message` (text) - Messaggio dell'eccezione
- `exception_file` (keyword) - File dove è stata lanciata l'eccezione
- `exception_line` (integer) - Linea nel file
- `stack_trace` (text) - Stack trace completo (solo per error/critical)

### Source location
- `file` (keyword) - File PHP dove è stato generato il log
- `line` (integer) - Linea nel file
- `class` (keyword) - Classe che ha generato il log (opzionale)
- `function` (keyword) - Funzione/metodo che ha generato il log (opzionale)

### Environment/Context
- `environment` (keyword) - APP_ENV (local, staging, production)
- `app_version` (keyword) - Versione dell'applicazione (da config o git)
- `hostname` (keyword) - Hostname del server
- `php_version` (keyword) - Versione PHP (es. "8.2.0")

### Correlation avanzata
- `parent_request_id` (keyword) - Request ID della chiamata parent (per async/nested)
- `trace_id` (keyword) - ID per distributed tracing (OpenTelemetry/W3C)
- `span_id` (keyword) - Span ID per distributed tracing
- `session_id` (keyword) - Session ID Laravel (per correlare sessioni utente)

### Performance/Monitoring
- `memory_usage_mb` (float) - Memoria usata al momento del log
- `memory_peak_mb` (float) - Picco memoria (già presente in job_log)

### Categorizzazione
- `tags` (keyword, array) - Tag per categorizzazione/filtri (es. ["payment", "critical"])

---

## Campi specifici per indice

### api_log

**Request context**:
- `user_agent` (text) - User agent del client
- `referer` (keyword) - Referrer URL
- `query_string` (text) - Query string parameters
- `request_body` (text) - Request body (opzionale, può essere grande)
- `response_size_bytes` (integer) - Dimensione risposta in bytes

**Headers** (oggetto):
- `request_headers` (object, dynamic: true) - Headers richiesta
- `response_headers` (object, dynamic: true) - Headers risposta

**Correlazione**:
- `correlation_id` (keyword) - ID esterno per correlazione con sistemi esterni

### general_log

**Entity tracking**:
- `model_id` (keyword) - ID specifico del modello (oltre a entity_type/entity_id)
- `changes` (object, dynamic: true) - Cambiamenti specifici (per audit trail)

**Business context**:
- `feature` (keyword) - Feature/modulo dell'app (es. "checkout", "user-management")
- `action_type` (keyword) - Tipo azione (create, update, delete, read, custom)

### job_log

**Job context**:
- `job_id` (keyword) - ID univoco del job (se disponibile)
- `queue_name` (keyword) - Nome della queue
- `attempts` (integer) - Numero tentativi
- `max_attempts` (integer) - Tentativi massimi
- `retry_after` (integer) - Secondi prima del retry

**Output**:
- `output` (text) - Output del comando/job
- `error_output` (text) - Error output separato

### integration_log

**Retry/Resilience**:
- `attempt` (integer) - Numero tentativo (per retry)
- `max_attempts` (integer) - Tentativi massimi
- `retry_after_ms` (integer) - Millisecondi prima del retry

**Correlazione esterna**:
- `external_id` (keyword) - ID fornito dal servizio esterno
- `correlation_id` (keyword) - ID per correlazione con sistemi esterni

**Timing dettagliato**:
- `dns_lookup_ms` (integer) - Tempo DNS lookup
- `connect_ms` (integer) - Tempo connessione
- `ssl_handshake_ms` (integer) - Tempo SSL handshake (se HTTPS)
- `time_to_first_byte_ms` (integer) - TTFB

**Request/Response metadata**:
- `request_size_bytes` (integer) - Dimensione richiesta
- `response_size_bytes` (integer) - Dimensione risposta
- `status_text` (keyword) - Testo status HTTP (es. "OK", "Not Found")

### orm_log

**Model tracking**:
- `model_id` (keyword) - ID specifico del modello (oltre al tipo)
- `model_key` (keyword) - Chiave primaria usata

**Transaction context**:
- `transaction_id` (keyword) - ID transazione database (se dentro transazione)
- `transaction_level` (integer) - Livello nested transaction

**Query analysis**:
- `query_type` (keyword) - Tipo query (SELECT, INSERT, UPDATE, DELETE)
- `query_hash` (keyword) - Hash della query (per raggruppare query simili)
- `is_slow_query` (boolean) - Flag se supera threshold (es. > 1000ms)

**Performance**:
- `rows_examined` (integer) - Righe esaminate (MySQL EXPLAIN)
- `rows_sent` (integer) - Righe inviate al client

---

## Priorità implementazione

### Alta priorità (essenziali per debugging)
1. **Exception tracking** (exception_type, exception_message, exception_file, exception_line, stack_trace)
2. **Source location** (file, line)
3. **Environment** (environment, hostname)
4. **Correlation avanzata** (parent_request_id, session_id)

### Media priorità (utili per analisi approfondita)
5. **Performance** (memory_usage_mb)
6. **Request context** per api_log (user_agent, referer, query_string)
7. **Transaction context** per orm_log (transaction_id)
8. **Retry info** per integration_log (attempt, max_attempts)

### Bassa priorità (nice to have)
9. **Distributed tracing** (trace_id, span_id)
10. **Tags** per categorizzazione
11. **Timing dettagliato** per integration_log
12. **Query analysis** avanzata per orm_log

---

## Note implementative

- **Stack trace**: Può essere molto grande, considerare di limitarlo o comprimerlo
- **Request/Response body**: Possono essere molto grandi, valutare se loggarli sempre o solo su error
- **Dynamic objects**: Usare `dynamic: true` per oggetti flessibili (headers, changes)
- **Performance**: Alcuni campi richiedono calcoli aggiuntivi (memory, timing), valutare impatto
