# Docker Structure

Tutti i file Docker per OpenSearch sono organizzati nella directory `docker/opensearch/` del package.

## Struttura

```
packages/laravel-logger/
└── docker/
    └── opensearch/
        ├── docker-compose.example.yml  # Configurazione Docker Compose
        ├── setup.php                   # Script PHP per template e policy
        ├── setup-dashboards.php        # Script per index patterns
        ├── setup.sh                    # Script shell (Linux/macOS)
        ├── setup.ps1                   # Script PowerShell (Windows)
        ├── README.md                   # Documentazione setup
        └── .env.example                # Esempio variabili ambiente
```

## Pubblicazione

Tutti i file possono essere pubblicati nel progetto con:

```bash
php artisan vendor:publish --tag=laravel-logger-docker
```

Questo copierà l'intera directory `docker/opensearch/` in `docker/opensearch/` del progetto.

## Utilizzo

### Dal Package (sviluppo)

```bash
# Avvia OpenSearch
cd packages/laravel-logger/docker/opensearch
docker-compose -f docker-compose.example.yml up -d

# Applica template
php packages/laravel-logger/docker/opensearch/setup.php
```

### Dopo Pubblicazione

```bash
# Avvia OpenSearch
cp docker/opensearch/docker-compose.example.yml docker-compose.yml
docker-compose up -d

# Applica template
php docker/opensearch/setup.php
```

## Vantaggi

- ✅ Tutto organizzato in un'unica directory
- ✅ Facile da pubblicare e condividere
- ✅ Struttura chiara e intuitiva
- ✅ Script cross-platform inclusi
