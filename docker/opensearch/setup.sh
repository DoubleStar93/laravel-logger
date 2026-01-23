#!/bin/bash

# Script per applicare template e ISM policy a OpenSearch locale

OPENSEARCH_URL="${OPENSEARCH_URL:-http://localhost:9200}"

echo "🚀 Setting up OpenSearch indices and policies..."
echo "   OpenSearch URL: $OPENSEARCH_URL"
echo ""

# Verifica che OpenSearch sia raggiungibile
if ! curl -s "$OPENSEARCH_URL" > /dev/null; then
    echo "❌ Error: OpenSearch non è raggiungibile su $OPENSEARCH_URL"
    echo "   Assicurati che docker-compose sia avviato: docker-compose up -d"
    exit 1
fi

echo "✅ OpenSearch è raggiungibile"
echo ""

# Funzione per applicare un template
apply_template() {
    local template_name=$1
    local template_file=$2
    
    echo "📋 Applying $template_name template..."
    if curl -s -X PUT "$OPENSEARCH_URL/_index_template/$template_name" \
        -H "Content-Type: application/json" \
        -d @opensearch/index-templates/$template_file > /dev/null; then
        echo "✅ $template_name template applicato"
        return 0
    else
        echo "❌ Errore nell'applicare il template $template_name"
        return 1
    fi
}

# Applica tutti i template
apply_template "api_log-template" "api_log-template.json" || exit 1
apply_template "general_log-template" "general_log-template.json" || exit 1
apply_template "cron_log-template" "cron_log-template.json" || exit 1
apply_template "integration_log-template" "integration_log-template.json" || exit 1
apply_template "orm_log-template" "orm_log-template.json" || exit 1

echo ""

# Applica ISM policy
echo "📋 Applying ISM retention policy..."
if curl -s -X PUT "$OPENSEARCH_URL/_plugins/_ism/policies/logs-retention-policy" \
    -H "Content-Type: application/json" \
    -d @opensearch/ism/logs-retention-policy.json > /dev/null; then
    echo "✅ ISM policy applicata"
else
    echo "❌ Errore nell'applicare la policy ISM"
    exit 1
fi

echo ""
echo "🎉 Setup completato!"
echo ""
echo "Puoi verificare gli indici con:"
echo "  curl $OPENSEARCH_URL/_cat/indices?v"
echo ""
echo "E aprire OpenSearch Dashboards su: http://localhost:5601"
