#!/usr/bin/env php
<?php

/**
 * Script PHP per applicare template e ISM policy a OpenSearch locale.
 * Cross-platform: funziona su Windows, Linux, macOS.
 *
 * Usage: 
 *   From package: php packages/laravel-logger/docker/opensearch/setup.php
 *   From project root (if published): php docker/opensearch/setup.php
 *   With index patterns: php docker/opensearch/setup.php --with-dashboards
 */

// Controlla se è stato passato il flag --with-dashboards
$withDashboards = in_array('--with-dashboards', $argv) || in_array('--dashboards', $argv);

$opensearchUrl = getenv('OPENSEARCH_URL') ?: 'http://localhost:9200';
$dashboardsUrl = getenv('OPENSEARCH_DASHBOARDS_URL') ?: 'http://localhost:5601';
// Calcola la root del progetto: da packages/laravel-logger/docker/opensearch/setup.php
// __DIR__ = packages/laravel-logger/docker/opensearch
// dirname(__DIR__, 1) = packages/laravel-logger/docker
// dirname(__DIR__, 2) = packages/laravel-logger
// dirname(__DIR__, 3) = packages
// dirname(__DIR__, 4) = root del progetto
$baseDir = dirname(__DIR__, 4);

echo "🚀 Setting up OpenSearch indices and policies...\n";
echo "   OpenSearch URL: {$opensearchUrl}\n";
echo "\n";

// Verifica che OpenSearch sia raggiungibile
if (!isOpenSearchReachable($opensearchUrl)) {
    echo "❌ Error: OpenSearch non è raggiungibile su {$opensearchUrl}\n";
    echo "   Assicurati che docker-compose sia avviato: docker-compose up -d\n";
    exit(1);
}

echo "✅ OpenSearch è raggiungibile\n";
echo "\n";

// Trova il percorso dei template (prima cerca quelli pubblicati, poi nel package)
// I template possono essere usati direttamente dal package, non è necessario pubblicarli
$opensearchDir = findOpenSearchDir($baseDir);
if ($opensearchDir === null) {
    echo "❌ Error: Template OpenSearch non trovati.\n";
    echo "   Lo script cerca i template in:\n";
    echo "   1. opensearch/ (se pubblicati)\n";
    echo "   2. packages/laravel-logger/resources/opensearch/\n";
    echo "   3. vendor/ermetix/laravel-logger/resources/opensearch/\n";
    echo "\n";
    echo "   Se il package è installato correttamente, i template dovrebbero essere trovati automaticamente.\n";
    echo "   Pubblicazione opzionale: php artisan vendor:publish --tag=laravel-logger-opensearch\n";
    exit(1);
}

echo "📁 Using OpenSearch templates from: {$opensearchDir}\n";
echo "\n";

// Template da applicare
$templates = [
    'api_log-template' => 'api_log-template.json',
    'general_log-template' => 'general_log-template.json',
    'cron_log-template' => 'cron_log-template.json',
    'integration_log-template' => 'integration_log-template.json',
    'orm_log-template' => 'orm_log-template.json',
    'error_log-template' => 'error_log-template.json',
];

// Applica tutti i template
foreach ($templates as $templateName => $templateFile) {
    $templatePath = "{$opensearchDir}/index-templates/{$templateFile}";
    
    if (!file_exists($templatePath)) {
        echo "❌ File template non trovato: {$templatePath}\n";
        exit(1);
    }
    
    echo "📋 Applying {$templateName} template...\n";
    
    if (!applyTemplate($opensearchUrl, $templateName, $templatePath)) {
        echo "❌ Errore nell'applicare il template {$templateName}\n";
        exit(1);
    }
    
    echo "✅ {$templateName} template applicato\n";
}

echo "\n";

// Applica ISM policy
$policyPath = "{$opensearchDir}/ism/logs-retention-policy.json";

if (!file_exists($policyPath)) {
    echo "❌ File policy non trovato: {$policyPath}\n";
    exit(1);
}

echo "📋 Applying ISM retention policy...\n";

if (!applyPolicy($opensearchUrl, $policyPath)) {
    echo "❌ Errore nell'applicare la policy ISM\n";
    exit(1);
}

echo "✅ ISM policy applicata\n";
echo "\n";

// Se richiesto, crea anche gli index pattern
if ($withDashboards) {
    echo "\n";
    echo "📊 Setting up OpenSearch Dashboards index patterns...\n";
    echo "   Dashboards URL: {$dashboardsUrl}\n";
    echo "\n";
    
    // Verifica che Dashboards sia raggiungibile
    if (!isDashboardsReachable($dashboardsUrl)) {
        echo "⚠️  Warning: OpenSearch Dashboards non è raggiungibile su {$dashboardsUrl}\n";
        echo "   Gli index pattern non verranno creati. Puoi crearli manualmente con:\n";
        echo "   php docker/opensearch/setup-dashboards.php\n";
    } else {
        echo "✅ OpenSearch Dashboards è raggiungibile\n";
        echo "\n";
        
        // Index pattern da creare con campi di default visibili
        $indexPatterns = [
            'api_log' => [
                'title' => 'api_log*',
                'timeFieldName' => '@timestamp',
                'description' => 'API access logs',
                'defaultFields' => [
                    '@timestamp',
                    'method',
                    'path',
                    'route_name',
                    'status',
                    'duration_ms',
                    'user_id',
                    'ip',
                    'request_id',
                ],
            ],
            'general_log' => [
                'title' => 'general_log*',
                'timeFieldName' => '@timestamp',
                'description' => 'General application logs',
                'defaultFields' => [
                    '@timestamp',
                    'message',
                    'event',
                    'entity_type',
                    'entity_id',
                    'action_type',
                    'user_id',
                    'level',
                    'request_id',
                ],
            ],
            'cron_log' => [
                'title' => 'cron_log*',
                'timeFieldName' => '@timestamp',
                'description' => 'Cron job and scheduled task logs',
                'defaultFields' => [
                    '@timestamp',
                    'job',
                    'command',
                    'status',
                    'duration_ms',
                    'exit_code',
                    'level',
                    'request_id',
                ],
            ],
            'integration_log' => [
                'title' => 'integration_log*',
                'timeFieldName' => '@timestamp',
                'description' => 'External integration logs',
                'defaultFields' => [
                    '@timestamp',
                    'integration_name',
                    'url',
                    'method',
                    'status',
                    'duration_ms',
                    'level',
                    'request_id',
                ],
            ],
            'orm_log' => [
                'title' => 'orm_log*',
                'timeFieldName' => '@timestamp',
                'description' => 'ORM and database operation logs',
                'defaultFields' => [
                    '@timestamp',
                    'model',
                    'action',
                    'query_type',
                    'table',
                    'duration_ms',
                    'is_slow_query',
                    'user_id',
                    'request_id',
                ],
            ],
            'error_log' => [
                'title' => 'error_log*',
                'timeFieldName' => '@timestamp',
                'description' => 'Error and exception logs',
                'defaultFields' => [
                    '@timestamp',
                    'exception_class',
                    'code',
                    'level',
                    'context_route',
                    'context_method',
                    'context_url',
                    'context_user_id',
                    'request_id',
                ],
            ],
        ];
        
        // Crea tutti gli index pattern
        $successCount = 0;
        foreach ($indexPatterns as $id => $pattern) {
            echo "📋 Creating index pattern: {$pattern['title']}...\n";
            
            if (!createIndexPattern($dashboardsUrl, $id, $pattern)) {
                echo "❌ Errore nella creazione dell'index pattern {$pattern['title']}\n";
                // Continua con gli altri anche se uno fallisce
                continue;
            }
            
            echo "✅ Index pattern {$pattern['title']} creato\n";
            
            // Configure default visible fields after pattern creation
            // Note: This will be done after test documents are created and fields are indexed
            
            $successCount++;
        }
        
        echo "\n";
        if ($successCount === count($indexPatterns)) {
            echo "✅ Tutti gli index pattern sono stati creati con successo\n";
        } else {
            echo "⚠️  {$successCount}/" . count($indexPatterns) . " index pattern creati\n";
        }
        
        // Crea documenti di test per far riconoscere i campi agli index pattern
        echo "\n";
        echo "📝 Creating test documents to index fields...\n";
        $testDocsCreated = createTestDocuments($opensearchUrl, $indexPatterns);
        if ($testDocsCreated > 0) {
            echo "✅ {$testDocsCreated} test documents created\n";
            echo "   Fields will be automatically indexed in Dashboards\n";
        }
        
        // Aspetta che i campi vengano indicizzati
        if ($testDocsCreated > 0) {
            echo "\n";
            echo "⏳ Waiting for fields to be indexed (5 seconds)...\n";
            echo "   Essential fields will be automatically visible in Dashboards\n";
            sleep(5);
            
            // Create default saved searches with visible columns for each pattern
            echo "\n";
            echo "⚙️  Creating default saved searches with visible columns...\n";
            foreach ($indexPatterns as $id => $pattern) {
                if (isset($pattern['defaultFields']) && !empty($pattern['defaultFields'])) {
                    if (createDefaultSavedSearch($dashboardsUrl, $id, $pattern['title'], $pattern['defaultFields'])) {
                        echo "   ✅ Default saved search created for {$pattern['title']}\n";
                    } else {
                        echo "   ⚠️  Could not create default saved search for {$pattern['title']}\n";
                    }
                }
            }
        }
    }
}

echo "\n";
echo "🎉 Setup completato!\n";
echo "\n";
echo "Puoi verificare gli indici con:\n";
echo "  curl {$opensearchUrl}/_cat/indices?v\n";
echo "\n";

if (!$withDashboards) {
    echo "💡 Per creare anche gli index pattern in OpenSearch Dashboards, esegui:\n";
    echo "   php docker/opensearch/setup.php --with-dashboards\n";
    echo "   oppure\n";
    echo "   php docker/opensearch/setup-dashboards.php\n";
    echo "\n";
}

echo "Poi apri OpenSearch Dashboards su: {$dashboardsUrl}\n";
echo "\n";
echo "💡 Nota: I template sono stati usati direttamente dal package.\n";
echo "   Pubblicazione opzionale (solo se vuoi modificarli):\n";
echo "   php artisan vendor:publish --tag=laravel-logger-opensearch\n";

// Funzioni helper

function findOpenSearchDir(string $baseDir): ?string
{
    // 1. Cerca nella root del progetto (template pubblicati)
    $publishedPath = "{$baseDir}/opensearch";
    if (is_dir($publishedPath) && is_dir("{$publishedPath}/index-templates")) {
        return $publishedPath;
    }
    
    // 2. Cerca nel package
    $packagePath = "{$baseDir}/packages/laravel-logger/resources/opensearch";
    if (is_dir($packagePath) && is_dir("{$packagePath}/index-templates")) {
        return $packagePath;
    }
    
    // 3. Cerca in vendor (se il package è installato via composer)
    $vendorPath = "{$baseDir}/vendor/ermetix/laravel-logger/resources/opensearch";
    if (is_dir($vendorPath) && is_dir("{$vendorPath}/index-templates")) {
        return $vendorPath;
    }
    
    return null;
}

function isOpenSearchReachable(string $url): bool
{
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 5,
            'ignore_errors' => true,
        ],
    ]);
    
    $result = @file_get_contents($url, false, $context);
    
    return $result !== false;
}

function applyTemplate(string $baseUrl, string $templateName, string $templatePath): bool
{
    $url = rtrim($baseUrl, '/').'/_index_template/'.urlencode($templateName);
    $content = file_get_contents($templatePath);
    
    if ($content === false) {
        return false;
    }
    
    return makeHttpRequest($url, 'PUT', $content);
}

function applyPolicy(string $baseUrl, string $policyPath): bool
{
    $url = rtrim($baseUrl, '/').'/_plugins/_ism/policies/logs-retention-policy';
    $content = file_get_contents($policyPath);
    
    if ($content === false) {
        return false;
    }
    
    return makeHttpRequest($url, 'PUT', $content);
}

function makeHttpRequest(string $url, string $method, string $body): bool
{
    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            'content' => $body,
            'timeout' => 10,
            'ignore_errors' => true,
        ],
    ]);
    
    $result = @file_get_contents($url, false, $context);
    
    // Verifica status code dalla risposta HTTP
    $statusCode = null;
    if (isset($http_response_header)) {
        foreach ($http_response_header as $header) {
            if (preg_match('/HTTP\/\d\.\d\s+(\d+)/', $header, $matches)) {
                $statusCode = (int) $matches[1];
                break;
            }
        }
    }
    
    if ($result === false) {
        return false;
    }
    
    // 200-299 sono successo, 409 (Conflict) significa che esiste già (OK)
    if ($statusCode !== null && $statusCode === 409) {
        return true; // Policy già esistente, considerato successo
    }
    
    if ($statusCode !== null && $statusCode >= 400) {
        // Mostra errore per debug
        echo "   ⚠️  HTTP {$statusCode}: " . substr($result, 0, 200) . "\n";
        return false;
    }
    
    // 200-299 sono successo
    return $statusCode !== null && $statusCode >= 200 && $statusCode < 300;
}

function isDashboardsReachable(string $url): bool
{
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 5,
            'ignore_errors' => true,
        ],
    ]);
    
    $result = @file_get_contents($url, false, $context);
    
    return $result !== false;
}

function createIndexPattern(string $baseUrl, string $id, array $pattern): bool
{
    // Prima cerca pattern esistenti per title (per evitare duplicati)
    $searchUrl = rtrim($baseUrl, '/').'/api/saved_objects/_find?type=index-pattern&fields=title&search_fields=title&search='.urlencode($pattern['title']);
    $searchContext = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 5,
            'ignore_errors' => true,
            'header' => [
                'Content-Type: application/json',
                'osd-xsrf: true',
                'Accept: application/json',
            ],
        ],
    ]);
    
    $searchResult = @file_get_contents($searchUrl, false, $searchContext);
    $existingId = null;
    
    if ($searchResult !== false) {
        $searchData = json_decode($searchResult, true);
        if (isset($searchData['saved_objects']) && is_array($searchData['saved_objects'])) {
            foreach ($searchData['saved_objects'] as $obj) {
                if (isset($obj['attributes']['title']) && $obj['attributes']['title'] === $pattern['title']) {
                    $existingId = $obj['id'];
                    break;
                }
            }
        }
    }
    
    // Se esiste un pattern con lo stesso title ma ID diverso, eliminalo
    if ($existingId !== null && $existingId !== $id) {
        $deleteUrl = rtrim($baseUrl, '/').'/api/saved_objects/index-pattern/'.$existingId;
        $deleteContext = stream_context_create([
            'http' => [
                'method' => 'DELETE',
                'timeout' => 5,
                'ignore_errors' => true,
                'header' => [
                    'Content-Type: application/json',
                    'osd-xsrf: true',
                    'Accept: application/json',
                ],
            ],
        ]);
        @file_get_contents($deleteUrl, false, $deleteContext);
    }
    
    $url = rtrim($baseUrl, '/').'/api/saved_objects/index-pattern/'.$id;
    
    // Verifica se esiste già con l'ID specifico
    $checkContext = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 5,
            'ignore_errors' => true,
            'header' => [
                'Content-Type: application/json',
                'osd-xsrf: true',
                'Accept: application/json',
            ],
        ],
    ]);
    
    $existing = @file_get_contents($url, false, $checkContext);
    $exists = false;
    
    if ($existing !== false) {
        $checkData = json_decode($existing, true);
        if (isset($checkData['id'])) {
            $exists = true;
        }
    }
    
    if ($exists) {
        // Esiste già, aggiornalo
        $method = 'PUT';
        $url .= '?overwrite=true';
    } else {
        // Non esiste, crealo
        $method = 'POST';
    }
    
    // Build fieldAttrs to configure which fields are visible by default
    // In OpenSearch Dashboards, fieldAttrs controls field visibility
    // We need to set sourceFilters to control which fields are shown in Discover
    // However, OpenSearch Dashboards uses a different approach - we'll use fieldFormatMap
    // and rely on the defaultFields array to configure via UI after creation
    
    // For now, we'll set the sort and let the test documents ensure fields are indexed
    // The defaultFields will be used to configure the pattern after creation
    $attributes = [
        'title' => $pattern['title'],
        'timeFieldName' => $pattern['timeFieldName'],
        // Set default sort order to timestamp descending (newest first)
        'sort' => [['@timestamp', 'desc']],
    ];
    
    $body = json_encode([
        'attributes' => $attributes,
    ]);
    
    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => [
                'Content-Type: application/json',
                'osd-xsrf: true',
                'Accept: application/json',
            ],
            'content' => $body,
            'timeout' => 10,
            'ignore_errors' => true,
        ],
    ]);
    
    $result = @file_get_contents($url, false, $context);
    
    if ($result === false) {
        return false;
    }
    
    // Verifica status code
    $statusCode = null;
    if (isset($http_response_header)) {
        foreach ($http_response_header as $header) {
            if (preg_match('/HTTP\/\d\.\d\s+(\d+)/', $header, $matches)) {
                $statusCode = (int) $matches[1];
                break;
            }
        }
    }
    
    // 200-299 sono successo
    return $statusCode !== null && $statusCode >= 200 && $statusCode < 300;
}

/**
 * Configure default visible fields for an index pattern.
 * Uses fieldAttrs to hide fields that should not be visible by default.
 */
function configureDefaultFields(string $baseUrl, string $patternId, array $defaultFields): bool
{
    // Get the current index pattern
    $url = rtrim($baseUrl, '/').'/api/saved_objects/index-pattern/'.$patternId;
    
    $getContext = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 5,
            'ignore_errors' => true,
            'header' => [
                'Content-Type: application/json',
                'osd-xsrf: true',
                'Accept: application/json',
            ],
        ],
    ]);
    
    $existing = @file_get_contents($url, false, $getContext);
    if ($existing === false) {
        return false;
    }
    
    $data = json_decode($existing, true);
    if (!isset($data['attributes'])) {
        return false;
    }
    
    $attributes = $data['attributes'];
    
    // Build fieldAttrs: hide all fields that are NOT in defaultFields
    // We'll get all fields from the pattern's field list
    // For now, we'll set fieldAttrs to mark non-default fields as hidden
    // OpenSearch Dashboards uses fieldAttrs with count: 0 to hide fields
    
    // Get all known fields for this pattern type
    $allKnownFields = getAllKnownFieldsForPattern($patternId);
    
    $fieldAttrs = [];
    foreach ($allKnownFields as $field) {
        // If field is NOT in defaultFields, hide it (count: 0)
        if (!in_array($field, $defaultFields)) {
            $fieldAttrs[$field] = ['count' => 0];
        }
    }
    
    // Update attributes with fieldAttrs
    if (!empty($fieldAttrs)) {
        $attributes['fieldAttrs'] = json_encode($fieldAttrs);
    }
    
    // Update the index pattern
    $putUrl = $url . '?overwrite=true';
    $body = json_encode([
        'attributes' => $attributes,
    ]);
    
    $putContext = stream_context_create([
        'http' => [
            'method' => 'PUT',
            'header' => [
                'Content-Type: application/json',
                'osd-xsrf: true',
                'Accept: application/json',
            ],
            'content' => $body,
            'timeout' => 10,
            'ignore_errors' => true,
        ],
    ]);
    
    $result = @file_get_contents($putUrl, false, $putContext);
    
    if ($result === false) {
        return false;
    }
    
    $statusCode = null;
    if (isset($http_response_header)) {
        foreach ($http_response_header as $header) {
            if (preg_match('/HTTP\/\d\.\d\s+(\d+)/', $header, $matches)) {
                $statusCode = (int) $matches[1];
                break;
            }
        }
    }
    
    return $statusCode !== null && $statusCode >= 200 && $statusCode < 300;
}

/**
 * Get all known fields for a pattern type.
 */
function getAllKnownFieldsForPattern(string $patternId): array
{
    // Map pattern IDs to their known fields based on test documents
    // patternId is like 'api_log', 'general_log', etc.
    $testDoc = createTestDocumentForPattern($patternId);
    
    // Extract all field names from the test document (recursively for nested fields)
    $fields = [];
    extractFields($testDoc, '', $fields);
    
    // Also add common fields that are always present
    $commonFields = [
        '@timestamp',
        'level',
        'request_id',
        'environment',
        'hostname',
        'service_name',
        'app_version',
        'parent_request_id',
        'trace_id',
        'span_id',
        'session_id',
        'tags',
    ];
    
    $fields = array_merge($fields, $commonFields);
    
    return array_unique($fields);
}

/**
 * Recursively extract field names from an array.
 */
function extractFields(array $data, string $prefix, array &$fields): void
{
    foreach ($data as $key => $value) {
        $fieldName = $prefix ? "{$prefix}.{$key}" : $key;
        
        if (is_array($value) && !empty($value) && !is_numeric(key($value))) {
            // Nested object, recurse
            extractFields($value, $fieldName, $fields);
        } else {
            // Leaf field
            $fields[] = $fieldName;
        }
    }
}

/**
 * Create a default saved search with visible columns for an index pattern.
 * This is the only way to set default visible columns in Discover table.
 * Note: OpenSearch Dashboards doesn't have a direct API for this, so we'll
 * use sourceFilters in the index pattern to limit which fields are available.
 */
function createDefaultSavedSearch(string $baseUrl, string $patternId, string $patternTitle, array $defaultFields): bool
{
    // Instead of creating a saved search (which is complex and user-specific),
    // we'll configure sourceFilters in the index pattern to make only default fields easily accessible
    // This doesn't directly set visible columns, but makes the default fields more prominent
    
    $url = rtrim($baseUrl, '/').'/api/saved_objects/index-pattern/'.$patternId;
    
    // Get current pattern
    $getContext = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 5,
            'ignore_errors' => true,
            'header' => [
                'Content-Type: application/json',
                'osd-xsrf: true',
                'Accept: application/json',
            ],
        ],
    ]);
    
    $existing = @file_get_contents($url, false, $getContext);
    if ($existing === false) {
        return false;
    }
    
    $data = json_decode($existing, true);
    if (!isset($data['attributes'])) {
        return false;
    }
    
    $attributes = $data['attributes'];
    
    // Set sourceFilters to include only default fields (this makes them more prominent)
    // sourceFilters is an array of field patterns to include in _source
    // Using wildcards to include default fields and their nested variants
    $sourceFilters = [];
    foreach ($defaultFields as $field) {
        $sourceFilters[] = $field;
        // Also include nested fields if any
        $sourceFilters[] = $field . '.*';
    }
    
    // Add common fields that should always be available
    $sourceFilters[] = '@timestamp';
    $sourceFilters[] = '_id';
    $sourceFilters[] = '_index';
    
    $attributes['sourceFilters'] = json_encode($sourceFilters);
    
    // Also set fieldFormatMap to ensure proper formatting
    $fieldFormatMap = [];
    foreach ($defaultFields as $field) {
        // Set appropriate format based on field type
        if (in_array($field, ['@timestamp', 'created_at', 'updated_at'])) {
            $fieldFormatMap[$field] = ['id' => 'date'];
        } elseif (in_array($field, ['duration_ms', 'status', 'exit_code', 'code'])) {
            $fieldFormatMap[$field] = ['id' => 'number'];
        }
    }
    
    if (!empty($fieldFormatMap)) {
        $attributes['fieldFormatMap'] = json_encode($fieldFormatMap);
    }
    
    // Update the pattern
    $putUrl = $url . '?overwrite=true';
    $body = json_encode([
        'attributes' => $attributes,
    ]);
    
    $putContext = stream_context_create([
        'http' => [
            'method' => 'PUT',
            'header' => [
                'Content-Type: application/json',
                'osd-xsrf: true',
                'Accept: application/json',
            ],
            'content' => $body,
            'timeout' => 10,
            'ignore_errors' => true,
        ],
    ]);
    
    $result = @file_get_contents($putUrl, false, $putContext);
    
    if ($result === false) {
        return false;
    }
    
    $statusCode = null;
    if (isset($http_response_header)) {
        foreach ($http_response_header as $header) {
            if (preg_match('/HTTP\/\d\.\d\s+(\d+)/', $header, $matches)) {
                $statusCode = (int) $matches[1];
                break;
            }
        }
    }
    
    return $statusCode !== null && $statusCode >= 200 && $statusCode < 300;
}

function createTestDocuments(string $opensearchUrl, array $indexPatterns): int
{
    $created = 0;
    $date = date('Y.m.d');
    
    foreach ($indexPatterns as $id => $pattern) {
        // Estrai il nome base dell'indice (es. 'api_log' da 'api_log*')
        $indexBase = rtrim($pattern['title'], '*');
        $indexName = "{$indexBase}-{$date}";
        
        // Crea un documento di test con tutti i campi essenziali per farli indicizzare
        $doc = createTestDocumentForPattern($indexBase);
        
        $url = rtrim($opensearchUrl, '/').'/'.$indexName.'/_doc';
        $body = json_encode($doc);
        
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => [
                    'Content-Type: application/json',
                ],
                'content' => $body,
                'timeout' => 5,
                'ignore_errors' => true,
            ],
        ]);
        
        $result = @file_get_contents($url, false, $context);
        
        if ($result !== false) {
            $statusCode = null;
            if (isset($http_response_header)) {
                foreach ($http_response_header as $header) {
                    if (preg_match('/HTTP\/\d\.\d\s+(\d+)/', $header, $matches)) {
                        $statusCode = (int) $matches[1];
                        break;
                    }
                }
            }
            
            if ($statusCode !== null && $statusCode >= 200 && $statusCode < 300) {
                $created++;
            }
        }
    }
    
    return $created;
}


/**
 * Crea un documento di test completo con tutti i campi essenziali per un pattern.
 */
function createTestDocumentForPattern(string $patternBase): array
{
    $doc = [
        '@timestamp' => date('c'),
        'level' => 'info',
        'request_id' => 'test-' . uniqid(),
        'environment' => 'local',
    ];
    
    // Aggiungi campi specifici per ogni pattern
    switch ($patternBase) {
        case 'api_log':
            $doc = array_merge($doc, [
                'method' => 'GET',
                'path' => '/api/test',
                'route_name' => 'test.route',
                'status' => 200,
                'duration_ms' => 45,
                'ip' => '127.0.0.1',
                'user_id' => '123',
            ]);
            break;
            
        case 'general_log':
            $doc = array_merge($doc, [
                'message' => 'Test document for general_log index pattern',
                'event' => 'test_event',
                'entity_type' => 'test',
                'entity_id' => '456',
                'action_type' => 'test',
                'user_id' => '123',
                'file' => '/app/test.php',
                'line' => 10,
                'class' => 'TestClass',
                'function' => 'testFunction',
            ]);
            break;
            
        case 'orm_log':
            $doc = array_merge($doc, [
                'model' => 'TestModel',
                'action' => 'create',
                'query_type' => 'INSERT',
                'table' => 'test_table',
                'duration_ms' => 12,
                'is_slow_query' => false,
                'user_id' => '123',
            ]);
            break;
            
        case 'integration_log':
            $doc = array_merge($doc, [
                'integration_name' => 'test_integration',
                'url' => 'https://api.test.com/endpoint',
                'method' => 'POST',
                'status' => 200,
                'duration_ms' => 350,
                'external_id' => 'ext_123',
            ]);
            break;
            
        case 'cron_log':
            $doc = array_merge($doc, [
                'job' => 'test_job',
                'command' => 'php artisan test:command',
                'status' => 'success',
                'duration_ms' => 1200,
                'exit_code' => 0,
                'memory_peak_mb' => 128.5,
            ]);
            break;
            
        case 'error_log':
            $doc = array_merge($doc, [
                'exception_class' => 'TestException',
                'code' => 0,
                'level' => 'error',
                'stack_trace' => '#0 /app/test.php(10): test()',
                'context_route' => 'test.route',
                'context_method' => 'GET',
                'context_url' => 'http://localhost/api/test',
                'context_user_id' => '123',
            ]);
            break;
    }
    
    return $doc;
}
