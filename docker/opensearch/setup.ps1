# PowerShell script per applicare template e ISM policy a OpenSearch locale

$OPENSEARCH_URL = if ($env:OPENSEARCH_URL) { $env:OPENSEARCH_URL } else { "http://localhost:9200" }

Write-Host "🚀 Setting up OpenSearch indices and policies..." -ForegroundColor Cyan
Write-Host "   OpenSearch URL: $OPENSEARCH_URL" -ForegroundColor Gray
Write-Host ""

# Verifica che OpenSearch sia raggiungibile
try {
    $response = Invoke-WebRequest -Uri $OPENSEARCH_URL -Method Get -UseBasicParsing -ErrorAction Stop
    Write-Host "✅ OpenSearch è raggiungibile" -ForegroundColor Green
} catch {
    Write-Host "❌ Error: OpenSearch non è raggiungibile su $OPENSEARCH_URL" -ForegroundColor Red
    Write-Host "   Assicurati che docker-compose sia avviato: docker-compose up -d" -ForegroundColor Yellow
    exit 1
}

Write-Host ""

# Funzione per applicare un template
function Apply-Template {
    param(
        [string]$TemplateName,
        [string]$TemplateFile
    )
    
    Write-Host "📋 Applying $TemplateName template..." -ForegroundColor Cyan
    $templatePath = Join-Path $PSScriptRoot "..\..\opensearch\index-templates\$TemplateFile"
    $templateContent = Get-Content $templatePath -Raw
    
    try {
        $response = Invoke-WebRequest -Uri "$OPENSEARCH_URL/_index_template/$TemplateName" `
            -Method Put `
            -ContentType "application/json" `
            -Body $templateContent `
            -UseBasicParsing `
            -ErrorAction Stop
        Write-Host "✅ $TemplateName template applicato" -ForegroundColor Green
        return $true
    } catch {
        Write-Host "❌ Errore nell'applicare il template $TemplateName" -ForegroundColor Red
        Write-Host $_.Exception.Message -ForegroundColor Red
        return $false
    }
}

# Applica tutti i template
$templates = @(
    @{ Name = "api_log-template"; File = "api_log-template.json" },
    @{ Name = "general_log-template"; File = "general_log-template.json" },
    @{ Name = "cron_log-template"; File = "cron_log-template.json" },
    @{ Name = "integration_log-template"; File = "integration_log-template.json" },
    @{ Name = "orm_log-template"; File = "orm_log-template.json" }
)

foreach ($template in $templates) {
    if (-not (Apply-Template -TemplateName $template.Name -TemplateFile $template.File)) {
        exit 1
    }
}

Write-Host ""

# Applica ISM policy
Write-Host "📋 Applying ISM retention policy..." -ForegroundColor Cyan
$policyPath = Join-Path $PSScriptRoot "..\..\opensearch\ism\logs-retention-policy.json"
$policyContent = Get-Content $policyPath -Raw

try {
    $response = Invoke-WebRequest -Uri "$OPENSEARCH_URL/_plugins/_ism/policies/logs-retention-policy" `
        -Method Put `
        -ContentType "application/json" `
        -Body $policyContent `
        -UseBasicParsing `
        -ErrorAction Stop
    Write-Host "✅ ISM policy applicata" -ForegroundColor Green
} catch {
    Write-Host "❌ Errore nell'applicare la policy ISM" -ForegroundColor Red
    Write-Host $_.Exception.Message -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "🎉 Setup completato!" -ForegroundColor Green
Write-Host ""
Write-Host "Puoi verificare gli indici con:" -ForegroundColor Cyan
Write-Host "  curl $OPENSEARCH_URL/_cat/indices?v" -ForegroundColor Gray
Write-Host ""
Write-Host "E aprire OpenSearch Dashboards su: http://localhost:5601" -ForegroundColor Cyan
