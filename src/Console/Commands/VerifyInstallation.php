<?php

namespace Ermetix\LaravelLogger\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class VerifyInstallation extends Command
{
    protected $signature = 'laravel-logger:verify';

    protected $description = 'Verify that the Laravel Logger package is correctly installed and configured';

    public function handle(): int
    {
        $this->info('🔍 Verifying Laravel Logger installation...');
        $this->newLine();

        $checks = [
            'package_installed' => $this->checkPackageInstalled(),
            'service_provider' => $this->checkServiceProvider(),
            'config_file' => $this->checkConfigFile(),
            'logging_channels' => $this->checkLoggingChannels(),
            'middleware' => $this->checkMiddleware(),
            'exception_handling' => $this->checkExceptionHandling(),
            'artisan_commands' => $this->checkArtisanCommands(),
            'container_bindings' => $this->checkContainerBindings(),
        ];

        $this->newLine();
        $this->displayResults($checks);

        $allPassed = !in_array(false, $checks, true);
        
        if ($allPassed) {
            $this->newLine();
            $this->info('✅ All checks passed! The package is correctly installed and configured.');
            return Command::SUCCESS;
        } else {
            $this->newLine();
            $this->warn('⚠️  Some checks failed. Please run: php artisan laravel-logger:install');
            return Command::FAILURE;
        }
    }

    protected function checkPackageInstalled(): bool
    {
        $this->line('   Checking package installation...');
        
        // Check if the package directory exists in vendor or packages
        $vendorPath = base_path('vendor/ermetix/laravel-logger');
        $packagePath = base_path('packages/laravel-logger');
        
        if (File::exists($vendorPath) || File::exists($packagePath)) {
            $this->info('      ✅ Package is installed');
            return true;
        }
        
        $this->error('      ❌ Package not found. Run: composer require ermetix/laravel-logger');
        return false;
    }

    protected function checkServiceProvider(): bool
    {
        $this->line('   Checking ServiceProvider...');
        
        if (class_exists('Ermetix\LaravelLogger\LaravelLoggerServiceProvider')) {
            $this->info('      ✅ ServiceProvider class exists');
            return true;
        }
        
        $this->error('      ❌ ServiceProvider not found');
        return false;
    }

    protected function checkConfigFile(): bool
    {
        $this->line('   Checking config file...');
        
        $configPath = config_path('laravel-logger.php');
        if (File::exists($configPath)) {
            $this->info('      ✅ Config file exists: ' . $configPath);
            return true;
        }
        
        $this->error('      ❌ Config file not found. Run: php artisan vendor:publish --tag=laravel-logger-config');
        return false;
    }

    protected function checkLoggingChannels(): bool
    {
        $this->line('   Checking logging channels...');
        
        $loggingPath = config_path('logging.php');
        if (!File::exists($loggingPath)) {
            $this->error('      ❌ config/logging.php not found');
            return false;
        }

        $content = File::get($loggingPath);
        $hasKafka = str_contains($content, "Ermetix\\LaravelLogger\\Logging\\CreateKafkaLogger");
        $hasOpenSearch = str_contains($content, "Ermetix\\LaravelLogger\\Logging\\CreateOpenSearchLogger");

        if ($hasKafka && $hasOpenSearch) {
            $this->info('      ✅ Kafka channel configured');
            $this->info('      ✅ OpenSearch channel configured');
            return true;
        }

        if (!$hasKafka) {
            $this->error('      ❌ Kafka channel not configured');
        }
        if (!$hasOpenSearch) {
            $this->error('      ❌ OpenSearch channel not configured');
        }
        
        return false;
    }

    protected function checkMiddleware(): bool
    {
        $this->line('   Checking middleware configuration...');
        
        $appPath = base_path('bootstrap/app.php');
        if (!File::exists($appPath)) {
            $this->error('      ❌ bootstrap/app.php not found');
            return false;
        }

        $content = File::get($appPath);
        $hasRequestId = str_contains($content, "Ermetix\\LaravelLogger\\Http\\Middleware\\RequestId");
        $hasApiAccessLog = str_contains($content, "Ermetix\\LaravelLogger\\Http\\Middleware\\ApiAccessLog");
        $hasFlushDeferred = str_contains($content, "Ermetix\\LaravelLogger\\Http\\Middleware\\FlushDeferredLogs");

        if ($hasRequestId && $hasApiAccessLog && $hasFlushDeferred) {
            $this->info('      ✅ RequestId middleware configured');
            $this->info('      ✅ ApiAccessLog middleware configured');
            $this->info('      ✅ FlushDeferredLogs middleware configured');
            return true;
        }

        if (!$hasRequestId) {
            $this->error('      ❌ RequestId middleware not configured');
        }
        if (!$hasApiAccessLog) {
            $this->error('      ❌ ApiAccessLog middleware not configured');
        }
        if (!$hasFlushDeferred) {
            $this->error('      ❌ FlushDeferredLogs middleware not configured');
        }
        
        return false;
    }

    protected function checkExceptionHandling(): bool
    {
        $this->line('   Checking exception handling...');
        
        $appPath = base_path('bootstrap/app.php');
        if (!File::exists($appPath)) {
            $this->error('      ❌ bootstrap/app.php not found');
            return false;
        }

        $content = File::get($appPath);
        $hasExceptionHandling = str_contains($content, "Ermetix\\LaravelLogger\\Support\\Logging\\Objects\\ErrorLogObject");

        if ($hasExceptionHandling) {
            $this->info('      ✅ Exception handling configured');
            return true;
        }

        $this->error('      ❌ Exception handling not configured');
        return false;
    }

    protected function checkArtisanCommands(): bool
    {
        $this->line('   Checking Artisan commands...');
        
        $commands = [
            'laravel-logger:install',
            'laravel-logger:verify',
            'opensearch:test',
            'opensearch:verify',
        ];

        $allFound = true;
        foreach ($commands as $command) {
            // Check if command is registered
            $commandExists = $this->getApplication()->has($command);
            if ($commandExists) {
                $this->info("      ✅ Command '{$command}' is available");
            } else {
                $this->error("      ❌ Command '{$command}' not found");
                $allFound = false;
            }
        }

        return $allFound;
    }

    protected function checkContainerBindings(): bool
    {
        $this->line('   Checking container bindings...');
        
        $bindings = [
            'Ermetix\LaravelLogger\Support\Logging\DeferredLogger',
            'Ermetix\LaravelLogger\Support\Logging\TypedLogger',
            'Ermetix\LaravelLogger\Support\Logging\MultiChannelLogger',
        ];

        $allBound = true;
        foreach ($bindings as $binding) {
            if (app()->bound($binding)) {
                $this->info("      ✅ {$binding} is bound");
            } else {
                $this->error("      ❌ {$binding} is not bound");
                $allBound = false;
            }
        }

        return $allBound;
    }

    protected function displayResults(array $checks): void
    {
        $this->info('📊 Verification Summary:');
        $this->newLine();

        $summary = [
            'Package Installed' => $checks['package_installed'],
            'ServiceProvider' => $checks['service_provider'],
            'Config File' => $checks['config_file'],
            'Logging Channels' => $checks['logging_channels'],
            'Middleware' => $checks['middleware'],
            'Exception Handling' => $checks['exception_handling'],
            'Artisan Commands' => $checks['artisan_commands'],
            'Container Bindings' => $checks['container_bindings'],
        ];

        foreach ($summary as $check => $passed) {
            $status = $passed ? '✅' : '❌';
            $this->line("   {$status} {$check}");
        }
    }
}
