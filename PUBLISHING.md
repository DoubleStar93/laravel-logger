# Guida alla Pubblicazione del Package

Questa guida ti aiuterà a pubblicare il package `ermetix/laravel-logger` su Packagist.

## Prerequisiti

1. **Account GitHub**: Crea un account su [GitHub](https://github.com) se non ce l'hai già
2. **Account Packagist**: Crea un account su [Packagist](https://packagist.org) se non ce l'hai già
3. **Repository GitHub**: Crea un nuovo repository pubblico su GitHub per il package

## Step 1: Preparazione del Package

### 1.1 Verifica dei File

Assicurati che i seguenti file siano presenti e corretti:

- ✅ `composer.json` - Configurato correttamente
- ✅ `README.md` - Completo e aggiornato
- ✅ `LICENSE` - Presente (MIT)
- ✅ `CHANGELOG.md` - Creato (vedi file nella root)
- ✅ `.gitignore` - Configurato per escludere file non necessari

### 1.2 Rimuovi File Non Necessari

Prima di pubblicare, assicurati di escludere:
- Directory `coverage/` (generata dai test)
- Directory `.tmp/` (file temporanei)
- File `composer.lock` (non necessario per i package)
- File di analisi/documentazione interna (es. `ANALISI_PROBLEMI_MIGLIORIE.md`, `VALUTAZIONE_*.md`)

**Nota:** Questi file sono già esclusi dal `.gitignore`, ma verifica che non siano stati committati.

### 1.3 Verifica composer.json

Il tuo `composer.json` dovrebbe avere:
- ✅ `name`: `ermetix/laravel-logger`
- ✅ `version`: `1.0.0` (o la versione che vuoi pubblicare)
- ✅ `description`: Descrizione chiara
- ✅ `license`: `MIT`
- ✅ `keywords`: Array di keywords rilevanti
- ✅ `require`: Dipendenze corrette
- ✅ `autoload`: Namespace corretto

## Step 2: Creare Repository GitHub

### 2.1 Crea il Repository

1. Vai su [GitHub](https://github.com/new)
2. Crea un nuovo repository pubblico chiamato `laravel-logger`
3. **NON** inizializzare con README, .gitignore o license (li hai già)

### 2.2 Inizializza Git nel Package

```bash
cd packages/laravel-logger

# Inizializza git se non è già inizializzato
git init

# Aggiungi il remote (sostituisci USERNAME con il tuo username GitHub)
git remote add origin https://github.com/USERNAME/laravel-logger.git

# Verifica lo stato
git status
```

### 2.3 Prima Commit

```bash
# Aggiungi tutti i file (il .gitignore escluderà automaticamente i file non necessari)
git add .

# Fai il commit iniziale
git commit -m "Initial release v1.0.0"

# Crea il branch main
git branch -M main

# Push al repository
git push -u origin main
```

### 2.4 Crea Tag per la Versione

```bash
# Crea un tag per la versione 1.0.0
git tag -a v1.0.0 -m "Release version 1.0.0"

# Push del tag
git push origin v1.0.0
```

## Step 3: Registrare su Packagist

### 3.1 Accedi a Packagist

1. Vai su [Packagist](https://packagist.org)
2. Accedi con il tuo account GitHub
3. Clicca su "Submit" in alto a destra

### 3.2 Inserisci l'URL del Repository

Inserisci l'URL del tuo repository GitHub:
```
https://github.com/USERNAME/laravel-logger
```

Clicca su "Check" per verificare che Packagist possa accedere al repository.

### 3.3 Completa la Registrazione

1. Packagist analizzerà il repository
2. Verifica che tutte le informazioni siano corrette
3. Clicca su "Submit"

## Step 4: Configurare Auto-Update (Opzionale ma Consigliato)

Per aggiornare automaticamente Packagist quando fai push su GitHub:

### 4.1 Crea GitHub Webhook

1. Vai sul tuo repository GitHub
2. Vai su **Settings** → **Webhooks**
3. Clicca su **Add webhook**
4. Inserisci:
   - **Payload URL**: `https://packagist.org/api/github?username=USERNAME`
   - **Content type**: `application/json`
   - **Secret**: Copia il token da Packagist (vedi sotto)
   - **Events**: Seleziona "Just the push event"
5. Clicca su **Add webhook**

### 4.2 Ottieni il Token da Packagist

1. Vai su [Packagist](https://packagist.org/profile/)
2. Vai su **Profile** → **Show API Token**
3. Copia il token e usalo come secret nel webhook

## Step 5: Verifica la Pubblicazione

### 5.1 Verifica su Packagist

Dopo qualche minuto, il package dovrebbe essere disponibile su:
```
https://packagist.org/packages/ermetix/laravel-logger
```

### 5.2 Testa l'Installazione

Crea un nuovo progetto Laravel e testa l'installazione:

```bash
# In un nuovo progetto Laravel
composer require ermetix/laravel-logger

# Verifica che sia installato correttamente
php artisan laravel-logger:verify
```

## Step 6: Aggiornamenti Futuri

Per pubblicare nuove versioni:

### 6.1 Aggiorna la Versione

1. Aggiorna `version` in `composer.json`
2. Aggiorna `CHANGELOG.md` con le nuove modifiche
3. Commit e push:

```bash
git add composer.json CHANGELOG.md
git commit -m "Bump version to 1.0.1"
git push
```

### 6.2 Crea Nuovo Tag

```bash
# Crea tag per la nuova versione
git tag -a v1.0.1 -m "Release version 1.0.1"
git push origin v1.0.1
```

Packagist aggiornerà automaticamente se hai configurato il webhook.

## Checklist Pre-Pubblicazione

Prima di pubblicare, verifica:

- [ ] `composer.json` è completo e corretto
- [ ] `README.md` è aggiornato e completo
- [ ] `LICENSE` è presente
- [ ] `CHANGELOG.md` è creato e aggiornato
- [ ] `.gitignore` esclude file non necessari
- [ ] Tutti i test passano: `composer test`
- [ ] Code coverage è buona (99.95% ✅)
- [ ] Non ci sono file sensibili (API keys, password, etc.)
- [ ] Il repository GitHub è pubblico
- [ ] Il tag della versione è creato e pushato

## Note Importanti

1. **Versioning**: Usa [Semantic Versioning](https://semver.org/)
   - `MAJOR.MINOR.PATCH` (es. 1.0.0)
   - MAJOR: breaking changes
   - MINOR: nuove features backward-compatible
   - PATCH: bug fixes backward-compatible

2. **Stabilità**: Il package usa `"minimum-stability": "dev"` e `"prefer-stable": true`, il che significa che Packagist preferirà versioni stabili quando disponibili.

3. **Documentazione**: Assicurati che il README sia completo perché sarà la prima cosa che gli utenti vedranno su Packagist.

4. **Test**: Prima di pubblicare, esegui tutti i test per assicurarti che tutto funzioni:
   ```bash
   composer test
   ```

## Troubleshooting

### Il package non appare su Packagist

- Verifica che il repository GitHub sia pubblico
- Controlla che il tag della versione sia pushato
- Verifica che `composer.json` sia valido: `composer validate`

### Packagist non si aggiorna automaticamente

- Verifica che il webhook GitHub sia configurato correttamente
- Controlla i log del webhook su GitHub
- Puoi forzare l'aggiornamento manualmente su Packagist (bottone "Update" nella pagina del package)

### Errori durante l'installazione

- Verifica che tutte le dipendenze siano corrette in `composer.json`
- Controlla che i namespace siano corretti
- Verifica che il ServiceProvider sia registrato correttamente

## Supporto

Per problemi o domande:
- Apri una issue su GitHub
- Controlla la documentazione nel README
- Verifica i log di Packagist per errori
