#!/usr/bin/env php
<?php

/**
 * Script PHP per creare gli index pattern in OpenSearch Dashboards.
 * Cross-platform: funziona su Windows, Linux, macOS.
 *
 * Usage: php docker/opensearch/setup-dashboards.php
 */

$dashboardsUrl = getenv('OPENSEARCH_DASHBOARDS_URL') ?: 'http://localhost:5601';
$baseDir = dirname(__DIR__, 2);

echo "🚀 Setting up OpenSearch Dashboards index patterns...\n";
echo "   Dashboards URL: {$dashboardsUrl}\n";
echo "\n";

// Verifica che Dashboards sia raggiungibile
if (!isDashboardsReachable($dashboardsUrl)) {
    echo "❌ Error: OpenSearch Dashboards non è raggiungibile su {$dashboardsUrl}\n";
    echo "   Assicurati che docker-compose sia avviato: docker-compose up -d\n";
    exit(1);
}

echo "✅ OpenSearch Dashboards è raggiungibile\n";
echo "\n";

// Index pattern da creare
$indexPatterns = [
    'api_log' => [
        'title' => 'api_log*',
        'timeFieldName' => '@timestamp',
        'description' => 'API access logs',
    ],
    'general_log' => [
        'title' => 'general_log*',
        'timeFieldName' => '@timestamp',
        'description' => 'General application logs',
    ],
    'job_log' => [
        'title' => 'job_log*',
        'timeFieldName' => '@timestamp',
        'description' => 'Job and scheduled task logs',
    ],
    'integration_log' => [
        'title' => 'integration_log*',
        'timeFieldName' => '@timestamp',
        'description' => 'External integration logs',
    ],
    'orm_log' => [
        'title' => 'orm_log*',
        'timeFieldName' => '@timestamp',
        'description' => 'ORM and database operation logs',
    ],
    'error_log' => [
        'title' => 'error_log*',
        'timeFieldName' => '@timestamp',
        'description' => 'Error and exception logs',
    ],
];

// Crea tutti gli index pattern
foreach ($indexPatterns as $id => $pattern) {
    echo "📋 Creating index pattern: {$pattern['title']}...\n";
    
    if (!createIndexPattern($dashboardsUrl, $id, $pattern)) {
        echo "❌ Errore nella creazione dell'index pattern {$pattern['title']}\n";
        // Continua con gli altri anche se uno fallisce
        continue;
    }
    
    echo "✅ Index pattern {$pattern['title']} creato\n";
}

echo "\n";
echo "🎉 Setup completato!\n";
echo "\n";
echo "Puoi aprire OpenSearch Dashboards su: {$dashboardsUrl}\n";
echo "Gli index pattern sono disponibili nel menu Discover.\n";

// Funzioni helper

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
    $url = rtrim($baseUrl, '/').'/api/saved_objects/index-pattern/'.$id;
    
    // Verifica se esiste già
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
    
    $body = json_encode([
        'attributes' => [
            'title' => $pattern['title'],
            'timeFieldName' => $pattern['timeFieldName'],
            // Set default sort order to timestamp descending (newest first)
            'sort' => [['@timestamp', 'desc']],
        ],
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
        // Debug: mostra l'errore
        global $http_response_header;
        if (isset($http_response_header)) {
            $errorMsg = '';
            foreach ($http_response_header as $header) {
                if (preg_match('/HTTP\/\d\.\d\s+(\d+)/', $header, $matches)) {
                    $statusCode = (int) $matches[1];
                    $errorMsg = "HTTP {$statusCode}";
                    break;
                }
            }
            if ($errorMsg) {
                echo "   ⚠️  {$errorMsg} - ";
            }
        }
        echo "Response: " . substr($result ?: 'No response', 0, 200) . "\n";
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
    $success = $statusCode !== null && $statusCode >= 200 && $statusCode < 300;
    
    if (!$success && $statusCode) {
        $responseData = json_decode($result, true);
        $errorMsg = $responseData['error']['message'] ?? $responseData['message'] ?? 'Unknown error';
        echo "   ⚠️  HTTP {$statusCode}: {$errorMsg}\n";
    }
    
    return $success;
}
