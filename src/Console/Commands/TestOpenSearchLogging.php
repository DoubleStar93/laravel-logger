<?php

namespace Ermetix\LaravelLogger\Console\Commands;

use Ermetix\LaravelLogger\Facades\LaravelLogger as Log;
use Ermetix\LaravelLogger\Support\Logging\Objects\ApiLogObject;
use Ermetix\LaravelLogger\Support\Logging\Objects\CronLogObject;
use Ermetix\LaravelLogger\Support\Logging\Objects\ErrorLogObject;
use Ermetix\LaravelLogger\Support\Logging\Objects\GeneralLogObject;
use Ermetix\LaravelLogger\Support\Logging\Objects\IntegrationLogObject;
use Ermetix\LaravelLogger\Support\Logging\Objects\OrmLogObject;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log as LaravelLog;

class TestOpenSearchLogging extends Command
{
    protected $signature = 'opensearch:test';

    protected $description = 'Test OpenSearch logging by creating sample log entries';

    public function handle(): int
    {
        $this->info('📝 Creating test log entries in OpenSearch...');
        $this->newLine();
        
        $this->line('Configuration:');
        $this->line('  OPENSEARCH_URL: '.env('OPENSEARCH_URL', 'http://localhost:9200'));
        $this->line('  Stack channels: '.json_encode(config('logging.channels.stack.channels', [])));
        $this->line('  LOG_STACK env: '.env('LOG_STACK', 'not set'));
        $this->newLine();
        
        // Test anche direttamente su opensearch channel
        $this->line('Also testing direct opensearch channel...');
        \Illuminate\Support\Facades\Log::channel('opensearch')->info('direct_test', [
            'log_index' => 'general_log',
            'test' => true,
        ]);
        $this->line('   ✅ Direct opensearch channel test sent');
        $this->newLine();

        // Test api_log
        $this->info('1. Testing api_log...');
        try {
            Log::api(new ApiLogObject(
                message: 'api_request',
                method: 'POST',
                path: '/api/users',
                routeName: 'users.store',
                status: 201,
                durationMs: 45,
                ip: '192.168.1.100',
                userId: '123',
                requestBody: json_encode(['name' => 'John Doe', 'email' => 'john@example.com']),
                responseBody: json_encode(['id' => 456, 'name' => 'John Doe', 'email' => 'john@example.com']),
                requestHeaders: ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
                responseHeaders: ['Content-Type' => 'application/json'],
                level: 'info',
            ));
            $this->line('   ✅ api_log entry created (with request/response body and headers)');
        } catch (\Throwable $e) {
            $this->error('   ❌ Error: '.$e->getMessage());
            $this->line('   Stack trace: '.$e->getTraceAsString());
        }

        // Test general_log
        $this->info('2. Testing general_log...');
        try {
            Log::general(new GeneralLogObject(
                message: 'user_profile_updated',
                event: 'profile_updated',
                userId: '123',
                entityType: 'user',
                entityId: '123',
                level: 'info',
            ));
            $this->line('   ✅ general_log entry created');
        } catch (\Throwable $e) {
            $this->error('   ❌ Error: '.$e->getMessage());
            $this->line('   Stack trace: '.$e->getTraceAsString());
        }

        // Test cron_log
        $this->info('3. Testing cron_log...');
        try {
            Log::cron(new CronLogObject(
                message: 'daily_report_completed',
                job: 'reports:daily',
                command: 'php artisan reports:daily',
                status: 'ok',
                durationMs: 1530,
                exitCode: 0,
                memoryPeakMb: 128.5,
                level: 'info',
            ));
            $this->line('   ✅ cron_log entry created');
        } catch (\Throwable $e) {
            $this->error('   ❌ Error: '.$e->getMessage());
            $this->line('   Stack trace: '.$e->getTraceAsString());
        }

        // Test integration_log
        $this->info('4. Testing integration_log...');
        try {
            Log::integration(new IntegrationLogObject(
                message: 'stripe_payment_processed',
                integrationName: 'stripe',
                url: 'https://api.stripe.com/v1/charges',
                method: 'POST',
                status: 200,
                durationMs: 320,
                requestBody: json_encode(['amount' => 1000, 'currency' => 'eur']),
                responseBody: json_encode(['id' => 'ch_123', 'status' => 'succeeded']),
                headers: ['Authorization' => 'Bearer sk_test_***'],
                level: 'info',
            ));
            $this->line('   ✅ integration_log entry created');
        } catch (\Throwable $e) {
            $this->error('   ❌ Error: '.$e->getMessage());
            $this->line('   Stack trace: '.$e->getTraceAsString());
        }

        // Test orm_log
        $this->info('5. Testing orm_log...');
        try {
            Log::orm(new OrmLogObject(
                message: 'user_updated',
                model: 'App\Models\User',
                action: 'update',
                query: 'UPDATE users SET email = ? WHERE id = ?',
                durationMs: 12,
                bindings: json_encode(['new@example.com', 123]),
                connection: 'mysql',
                table: 'users',
                userId: '123',
                previousValue: ['email' => 'old@example.com', 'name' => 'John'],
                afterValue: ['email' => 'new@example.com', 'name' => 'John'],
                level: 'info',
            ));
            $this->line('   ✅ orm_log entry created');
        } catch (\Throwable $e) {
            $this->error('   ❌ Error: '.$e->getMessage());
            $this->line('   Stack trace: '.$e->getTraceAsString());
        }

        // Test error_log
        $this->info('6. Testing error_log...');
        try {
            Log::error(new ErrorLogObject(
                message: 'test_exception',
                stackTrace: '#0 /app/test.php(10): test()',
                exceptionClass: 'Exception',
                file: '/app/test.php',
                line: 5,
                code: 0,
                level: 'error',
            ));
            $this->line('   ✅ error_log entry created');
        } catch (\Throwable $e) {
            $this->error('   ❌ Error: '.$e->getMessage());
            $this->line('   Stack trace: '.$e->getTraceAsString());
        }

        $this->newLine();
        $this->info('⏳ Waiting 2 seconds for OpenSearch to index...');
        sleep(2);
        $this->newLine();
        
        $this->info('🎉 Test completed!');
        $this->newLine();
        $this->line('Run: php artisan opensearch:verify');
        $this->line('Or check OpenSearch Dashboards: http://localhost:5601');

        return Command::SUCCESS;
    }
}
