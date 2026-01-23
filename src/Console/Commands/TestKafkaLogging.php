<?php

namespace Ermetix\LaravelLogger\Console\Commands;

use Ermetix\LaravelLogger\Jobs\LogToKafka;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestKafkaLogging extends Command
{
    protected $signature = 'kafka:test 
                            {message? : The message to send}
                            {--count=1 : Number of messages to send}
                            {--async : Dispatch to queue instead of running synchronously}';

    protected $description = 'Test Kafka logging by sending sample messages via REST Proxy';

    public function handle(): int
    {
        $this->info('📝 Testing Kafka logging...');
        $this->newLine();
        
        $this->line('Configuration:');
        $this->line('  KAFKA_REST_PROXY_URL: '.env('KAFKA_REST_PROXY_URL', 'http://localhost:8082'));
        $this->line('  KAFKA_LOG_TOPIC: '.env('KAFKA_LOG_TOPIC', 'laravel-logs'));
        $this->line('  queue: '.($this->option('async') ? 'async (dispatch)' : 'sync (dispatchSync)'));
        $this->newLine();
        
        $message = (string) ($this->argument('message') ?? 'kafka_test');
        $count = max(1, (int) $this->option('count'));
        $async = (bool) $this->option('async');

        // Test also directly on kafka channel
        $this->line('Also testing direct kafka channel...');
        try {
            Log::channel('kafka')->info('direct_test', [
                'log_index' => 'general_log',
                'test' => true,
            ]);
            $this->line('   ✅ Direct kafka channel test sent');
        } catch (\Throwable $e) {
            $this->error('   ❌ Error: '.$e->getMessage());
        }
        $this->newLine();

        $this->info("Dispatching {$count} Kafka test message(s)...");

        for ($i = 1; $i <= $count; $i++) {
            try {
                $job = new LogToKafka($message, [
                    'sequence' => $i,
                    'log_index' => 'general_log',
                    'source' => 'artisan:kafka:test',
                ]);

                if ($async) {
                    dispatch($job);
                    $this->line("   ✅ Message {$i} dispatched to queue");
                } else {
                    dispatch_sync($job);
                    $this->line("   ✅ Message {$i} sent");
                }
            } catch (\Throwable $e) {
                $this->error("   ❌ Error sending message {$i}: ".$e->getMessage());
            }
        }

        $this->newLine();
        $this->info('🎉 Test completed!');
        $this->newLine();
        
        if ($async) {
            $this->line('Now run a worker: php artisan queue:work (or queue:listen)');
        }
        
        $this->line('View messages in Kafka UI: http://localhost:8080');
        $this->line('Or use console consumer: docker exec -it kafka kafka-console-consumer --bootstrap-server localhost:29092 --topic laravel-logs --from-beginning');

        return Command::SUCCESS;
    }
}
