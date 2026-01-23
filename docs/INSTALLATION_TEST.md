# Test di Installazione

## Verifica Installazione

### 1. Verifica Package Installato

```bash
composer show ermetix/laravel-logger
```

### 2. Verifica ServiceProvider Registrato

```bash
php artisan package:discover
```

Dovresti vedere il package nella lista dei package scoperti.

### 3. Verifica Comandi Disponibili

```bash
php artisan list | grep -i "opensearch\|laravel-logger"
```

Dovresti vedere:
- `opensearch:test`
- `opensearch:verify`
- `laravel-logger:install`

### 4. Test Comando Install

```bash
php artisan laravel-logger:install --help
```

Dovrebbe mostrare l'help del comando.

### 5. Test Comando OpenSearch

```bash
php artisan opensearch:test --help
```

Dovrebbe mostrare l'help del comando.

## Test Completo Installazione

### Scenario: Installazione da Zero

1. **Pulisci configurazione esistente** (se presente):
   ```bash
   rm -f config/laravel-logger.php
   # Rimuovi manualmente i canali da config/logging.php
   # Rimuovi manualmente i middleware da bootstrap/app.php
   ```

2. **Esegui installazione**:
   ```bash
   php artisan laravel-logger:install
   ```

3. **Verifica risultati**:
   - ✅ `config/laravel-logger.php` esiste
   - ✅ `config/logging.php` contiene canali `kafka` e `opensearch`
   - ✅ `bootstrap/app.php` contiene middleware e exception handling

4. **Test funzionalità**:
   ```bash
   php artisan opensearch:test
   ```

## Troubleshooting

### ServiceProvider non trovato

```bash
composer dump-autoload
php artisan package:discover
```

### Comandi non visibili

```bash
php artisan config:clear
php artisan cache:clear
php artisan package:discover
```

### Class not found

Verifica che il package sia installato:
```bash
composer show ermetix/laravel-logger
```

Se non è installato:
```bash
composer require ermetix/laravel-logger
```
