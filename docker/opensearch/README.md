# OpenSearch Docker Setup

Questa directory contiene tutti i file necessari per avviare OpenSearch e OpenSearch Dashboards localmente:
- `docker-compose.example.yml` - Configurazione Docker Compose
- `setup.php` - Script per applicare template e policy
- `setup-dashboards.php` - Script per creare index patterns
- `setup.sh` / `setup.ps1` - Script shell alternativi

## Avvio rapido

### 1. Avvia OpenSearch

Il `docker-compose.example.yml` è incluso in questa directory. Puoi:

**Opzione A: Copiare nella root del progetto**
```bash
cp docker/opensearch/docker-compose.example.yml docker-compose.yml
docker-compose up -d
```

**Opzione B: Usare direttamente dalla directory**
```bash
cd docker/opensearch
docker-compose -f docker-compose.example.yml up -d
```

### 2. Verifica che OpenSearch sia attivo

```bash
curl http://localhost:9200
```

Dovresti vedere una risposta JSON con informazioni sul cluster.

### 3. ⚠️ IMPORTANTE: Applica template e policy

**Prima di loggare**, devi applicare i template agli indici. 

I template sono inclusi nel package `ermetix/laravel-logger`. Lo script cercherà automaticamente i template:
1. Nella root del progetto (se pubblicati)
2. Nel package (`packages/laravel-logger/resources/opensearch`)
3. In vendor (se installato via composer)

Esegui lo script PHP incluso (cross-platform):

```bash
# Setup base (template e policy)
php docker/opensearch/setup.php

# Setup completo (template, policy + index pattern)
php docker/opensearch/setup.php --with-dashboards
```

**Nota:** I template sono gestiti dal package e non devono essere duplicati nella root del progetto.

**Alternative:**
- **Linux/macOS:** `bash docker/opensearch/setup.sh` (usa lo script PHP per compatibilità cross-platform)
- **Windows (PowerShell):** `.\docker\opensearch\setup.ps1`

Lo script applica:
- 6 index template (api_log, general_log, cron_log, integration_log, orm_log, error_log)
- ISM retention policy
- (Opzionale con `--with-dashboards`) Index pattern in OpenSearch Dashboards

**Senza questo passo, i log non verranno indicizzati correttamente!**

### 4. 📊 Crea gli index pattern in OpenSearch Dashboards

**Per visualizzare i log in Dashboards**, hai due opzioni:

**Opzione A: Usa il flag `--with-dashboards` (consigliato)**
```bash
php docker/opensearch/setup.php --with-dashboards
```

**Opzione B: Script dedicato**
```bash
php docker/opensearch/setup-dashboards.php
```

Entrambi creano automaticamente tutti gli index pattern necessari:
- `api_log*`
- `general_log*`
- `cron_log*`
- `integration_log*`
- `orm_log*`
- `error_log*`

Dopo aver eseguito lo script, apri OpenSearch Dashboards su http://localhost:5601 e vai su **Discover** per vedere i log.

### 4. Configura Laravel (.env)

## Configurazione Laravel

Nel tuo `.env`, assicurati di avere:

```env
OPENSEARCH_URL=http://localhost:9200
OPENSEARCH_VERIFY_TLS=false
OPENSEARCH_SILENT=true
OPENSEARCH_TIMEOUT=2
OPENSEARCH_DEFAULT_INDEX=general_log
```

**Nota**: `OPENSEARCH_VERIFY_TLS=false` è necessario perché il Docker locale non usa HTTPS.

**Riferimento completo**: Vedi `packages/laravel-logger/env.example` per tutte le variabili disponibili.

## Verifica setup

Dopo aver eseguito lo script di setup, puoi verificare che i template siano stati applicati:

```bash
# Lista tutti i template
curl "http://localhost:9200/_index_template?pretty"

# Verifica un template specifico
curl "http://localhost:9200/_index_template/api_log-template?pretty"
```

### Setup manuale (alternativa)

Dopo aver avviato OpenSearch, puoi applicare i template e la policy ISM manualmente:

```bash
# Template (mapping) - applica tutti e 5 i template
curl -X PUT "http://localhost:9200/_index_template/api_log-template" \
  -H "Content-Type: application/json" \
  -d @opensearch/index-templates/api_log-template.json

curl -X PUT "http://localhost:9200/_index_template/general_log-template" \
  -H "Content-Type: application/json" \
  -d @opensearch/index-templates/general_log-template.json

curl -X PUT "http://localhost:9200/_index_template/cron_log-template" \
  -H "Content-Type: application/json" \
  -d @opensearch/index-templates/cron_log-template.json

curl -X PUT "http://localhost:9200/_index_template/integration_log-template" \
  -H "Content-Type: application/json" \
  -d @opensearch/index-templates/integration_log-template.json

curl -X PUT "http://localhost:9200/_index_template/orm_log-template" \
  -H "Content-Type: application/json" \
  -d @opensearch/index-templates/orm_log-template.json

# ISM Policy (retention)
curl -X PUT "http://localhost:9200/_plugins/_ism/policies/logs-retention-policy" \
  -H "Content-Type: application/json" \
  -d @opensearch/ism/logs-retention-policy.json
```

**Nota**: Il progetto usa 5 template separati (uno per ogni indice: api_log, general_log, cron_log, integration_log, orm_log) con mapping strict (dynamic: false) per controllo totale sui campi.

## Test logging

```php
use App\Support\Facades\AppLog as Log;
use App\Support\Logging\Objects\GeneralLogObject;

Log::general(new GeneralLogObject(
    message: 'test_from_docker',
    context: ['foo' => 'bar'],
    level: 'info',
));
```

Poi verifica su OpenSearch Dashboards o via API:

```bash
curl "http://localhost:9200/general_log/_search?pretty"
```

## Stop

```bash
docker-compose down
```

Se hai usato il file dalla directory opensearch:
```bash
cd docker/opensearch
docker-compose -f docker-compose.example.yml down
```

Per rimuovere anche i volumi (dati):

```bash
docker-compose down -v
```
