# Kafka Docker Setup

Questo setup include:
- **Zookeeper** (porta `2181`)
- **Kafka** (porta `9092` per host, `29092` per container)
- **Kafka REST Proxy** (porta `8082`) - usato dal package
- **Kafka UI** (porta `8080`) - interfaccia web opzionale

## Setup

```bash
# Copia il file di esempio
cp docker-compose.example.yml docker-compose.yml

# Avvia i servizi
docker compose up -d

# Verifica che siano attivi
docker compose ps
```

## Configurazione .env

Aggiungi al tuo `.env`:

```env
KAFKA_REST_PROXY_URL=http://localhost:8082
KAFKA_LOG_TOPIC=laravel-logs
KAFKA_LOG_TIMEOUT=2
KAFKA_LOG_SILENT=true
```

## Visualizzare i messaggi

### 1. Kafka UI (interfaccia web)

Apri `http://localhost:8080` nel browser:
- Vai su **Topics** → `laravel-logs`
- Clicca sulla tab **Messages**
- I messaggi appaiono in tempo reale

### 2. Console Consumer (da terminale)

```bash
# Entra nel container Kafka
docker exec -it kafka bash

# Consuma messaggi dal topic (dall'inizio)
kafka-console-consumer \
  --bootstrap-server localhost:29092 \
  --topic laravel-logs \
  --from-beginning

# Oppure consuma solo nuovi messaggi
kafka-console-consumer \
  --bootstrap-server localhost:29092 \
  --topic laravel-logs
```

Oppure direttamente da host (se hai kafka tools installati):

```bash
docker exec -it kafka kafka-console-consumer \
  --bootstrap-server localhost:29092 \
  --topic laravel-logs \
  --from-beginning
```

### 3. Kafka REST Proxy API

```bash
# Lista dei topics
curl http://localhost:8082/topics

# Consuma messaggi (richiede un consumer group)
curl -X POST http://localhost:8082/consumers/my-group \
  -H "Content-Type: application/vnd.kafka.v2+json" \
  -d '{"name": "my-consumer", "format": "json"}'

# Subscribe al topic
curl -X POST http://localhost:8082/consumers/my-group/instances/my-consumer/subscription \
  -H "Content-Type: application/vnd.kafka.v2+json" \
  -d '{"topics": ["laravel-logs"]}'

# Leggi messaggi
curl http://localhost:8082/consumers/my-group/instances/my-consumer/records \
  -H "Accept: application/vnd.kafka.json.v2+json"
```

## Test

Dopo aver avviato i servizi, puoi testare l'invio di messaggi:

```bash
php artisan kafka:test
```

Poi visualizza i messaggi usando uno dei metodi sopra.

## Stop

```bash
docker compose down
```

## Troubleshooting

- Se Kafka UI non si connette, verifica che il container `kafka` sia attivo
- Se non vedi messaggi, verifica che il topic `laravel-logs` esista (viene creato automaticamente se `KAFKA_AUTO_CREATE_TOPICS_ENABLE=true`)
- Per vedere i log dei container: `docker compose logs kafka`
