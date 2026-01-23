# Meccanismo di Auto-Flush per DeferredLogger

## Panoramica

Il `DeferredLogger` ora supporta un meccanismo di auto-flush automatico che previene l'esaurimento della memoria quando si accumulano troppi log in memoria. Quando viene raggiunto il limite configurabile, tutti i log accumulati vengono automaticamente scritti (flushed) e l'esecuzione continua normalmente.

## Configurazione

La configurazione si trova in `config/laravel-logger.php` nella sezione `deferred`:

```php
'deferred' => [
    /*
    | Maximum number of logs to accumulate in memory before automatically
    | flushing. When this limit is reached, all accumulated logs are
    | flushed and execution continues normally.
    |
| Set to 0 or null to disable the limit (not recommended in production).
| Default: 1000 logs (~1 MB based on average log size)
    |
    */
    'max_logs' => (int) env('LOG_DEFERRED_MAX_LOGS', 1000),

    /*
    | Whether to log a warning when the limit is reached and auto-flush
    | is triggered. Useful for monitoring memory usage patterns.
    |
    */
    'warn_on_limit' => filter_var(env('LOG_DEFERRED_WARN_ON_LIMIT', true), FILTER_VALIDATE_BOOL),
],
```

### Variabili d'Ambiente

- `LOG_DEFERRED_MAX_LOGS`: Numero massimo di log da accumulare prima dell'auto-flush (default: 1000)
- `LOG_DEFERRED_WARN_ON_LIMIT`: Se `true`, logga un warning quando viene raggiunto il limite (default: true)

## Come Funziona

1. **Accumulo Normale**: I log vengono accumulati in memoria come prima
2. **Controllo del Limite**: Ad ogni chiamata a `defer()`, viene controllato se il limite è stato raggiunto
3. **Auto-Flush**: Se il limite è raggiunto:
   - Viene loggato un warning (se `warn_on_limit` è `true`)
   - Tutti i log accumulati vengono scritti immediatamente
   - Il contatore viene resettato
   - L'esecuzione continua normalmente
4. **Flush Finale**: Alla fine della request/job, viene eseguito un flush finale di eventuali log rimanenti

## Esempio di Utilizzo

```php
// Il DeferredLogger viene configurato automaticamente dal ServiceProvider
// basandosi sulla configurazione

// Scenario: limite di 5 log
$logger = app(DeferredLogger::class);

// Aggiungi 5 log - al 5° log viene triggerato l'auto-flush
for ($i = 1; $i <= 5; $i++) {
    $logger->defer('opensearch', 'info', "Message $i", ['key' => "value$i"]);
}

// Dopo l'auto-flush, il count è 0
expect($logger->count())->toBe(0);

// Puoi continuare ad aggiungere log normalmente
$logger->defer('opensearch', 'info', 'Message 6', ['key' => 'value6']);
expect($logger->count())->toBe(1);

// Alla fine della request/job, viene eseguito un flush finale
```

## Metodi Aggiunti

### `getAutoFlushCount(): int`
Restituisce il numero di volte che l'auto-flush è stato triggerato durante la request/job corrente.

```php
$logger = app(DeferredLogger::class);
// ... aggiungi log fino al limite ...
$autoFlushCount = $logger->getAutoFlushCount(); // es: 3
```

### `getMaxLogs(): ?int`
Restituisce il limite massimo configurato (o `null` se disabilitato).

```php
$logger = app(DeferredLogger::class);
$maxLogs = $logger->getMaxLogs(); // es: 1000
```

## Comportamento

### Con Limite Abilitato (default)
- I log vengono accumulati fino al limite
- Quando il limite è raggiunto, viene eseguito un auto-flush
- L'esecuzione continua normalmente
- Il processo può ripetersi se vengono aggiunti altri log
- Alla fine viene eseguito un flush finale

### Con Limite Disabilitato (`max_logs = 0` o `null`)
- I log vengono accumulati senza limiti
- Nessun auto-flush viene eseguito
- Solo il flush finale alla fine della request/job
- **⚠️ Non raccomandato in produzione** - può causare esaurimento della memoria

## Warning e Monitoring

Quando `warn_on_limit` è `true` (default), viene loggato un warning ogni volta che viene raggiunto il limite.

**Dove viene loggato:**
- Il warning viene scritto nel canale `single` (file `storage/logs/laravel.log`)
- Viene scritto immediatamente, bypassando il `DeferredLogger` per evitare ricorsioni
- Non viene incluso nei log deferred, quindi non contribuisce al conteggio dei log accumulati

**Formato del warning:**
```
[warning] DeferredLogger: Maximum log limit reached, auto-flushing
{
    "limit": 1000,
    "logs_flushed": 1000,
    "auto_flush_count": 1
}
```

Questo è utile per:
- Monitorare pattern di utilizzo della memoria
- Identificare request/job che generano molti log
- Ottimizzare la configurazione del limite

## Raccomandazioni

1. **Limite Default (1000)**: Adatto per la maggior parte delle applicazioni
   - ~1 MB di memoria (basato su stime)
   - Bilanciato tra performance e sicurezza

2. **Applicazioni con Molti Log**: Considera di aumentare il limite
   - Se vedi molti warning, aumenta `LOG_DEFERRED_MAX_LOGS`
   - Monitora la memoria utilizzata

3. **Applicazioni con Pochi Log**: Puoi ridurre il limite
   - Riduce l'uso di memoria
   - Aumenta la frequenza di flush (minore impatto sulle performance)

4. **Monitoring**: Monitora `auto_flush_count` per identificare pattern
   - Se `auto_flush_count > 5` in una singola request, considera di ottimizzare

## Compatibilità

- ✅ Compatibile con il comportamento esistente
- ✅ Il flush finale continua a funzionare normalmente
- ✅ Supporta batch processing come prima
- ✅ Non rompe il codice esistente

## Test

Sono stati aggiunti test completi in `tests/Unit/DeferredLoggerTest.php` per verificare:
- Auto-flush quando il limite è raggiunto
- Continuazione normale dopo auto-flush
- Multiple auto-flush durante la stessa request
- Warning quando abilitato
- Nessun warning quando disabilitato
- Comportamento con limite disabilitato
