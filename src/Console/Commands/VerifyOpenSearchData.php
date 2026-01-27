<?php

namespace Ermetix\LaravelLogger\Console\Commands;

use Illuminate\Console\Command;

class VerifyOpenSearchData extends Command
{
    protected $signature = 'opensearch:verify';

    protected $description = 'Verify that log data exists in OpenSearch indices';

    public function handle(): int
    {
        $opensearchUrl = env('OPENSEARCH_URL', 'http://localhost:9200');
        $indices = ['api_log', 'general_log', 'job_log', 'integration_log', 'orm_log', 'error_log'];

        $this->info('🔍 Verifying OpenSearch indices...');
        $this->newLine();

        foreach ($indices as $index) {
            // Prima verifica se l'indice esiste
            $indexUrl = rtrim($opensearchUrl, '/').'/'.$index;
            $countUrl = rtrim($opensearchUrl, '/').'/'.$index.'/_count';
            
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => 5,
                    'ignore_errors' => true,
                ],
            ]);

            $indexExists = @file_get_contents($indexUrl, false, $context) !== false;
            
            if (!$indexExists) {
                $this->warn("   ⚠️  {$index}: index does not exist yet");
                continue;
            }

            // Conta i documenti
            $countResponse = @file_get_contents($countUrl, false, $context);
            
            if ($countResponse === false) {
                $this->error("   ❌ {$index}: could not get count");
                continue;
            }

            $countData = json_decode($countResponse, true);
            $count = $countData['count'] ?? 0;

            if ($count > 0) {
                $this->info("   ✅ {$index}: {$count} document(s)");
            } else {
                $this->warn("   ⚠️  {$index}: exists but has no documents");
            }
        }

        $this->newLine();
        $this->info('💡 Tip: Run "php artisan opensearch:test" to create test data');

        return Command::SUCCESS;
    }
}
