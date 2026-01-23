<?php

namespace Ermetix\LaravelLogger\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallLogger extends Command
{
    protected $signature = 'laravel-logger:install 
                            {--force : Overwrite existing configuration}';

    protected $description = 'Install Laravel Logger package configuration';

    public function handle(): int
    {
        $this->info('🚀 Installing Laravel Logger package...');
        $this->newLine();

        // 1. Publish config
        $this->info('1. Publishing configuration...');
        $this->call('vendor:publish', [
            '--tag' => 'laravel-logger-config',
            '--force' => $this->option('force'),
        ]);

        // 2. Add logging channels to config/logging.php
        $this->info('2. Adding logging channels to config/logging.php...');
        $this->addLoggingChannels();

        // 3. Add middleware to bootstrap/app.php
        $this->info('3. Adding middleware configuration to bootstrap/app.php...');
        $this->addMiddlewareConfiguration();

        // 4. Publish OpenSearch templates (optional)
        // Note: Templates can be used directly from the package. Publishing is only needed if you want to customize them.
        // The setup scripts will automatically find templates in: package -> vendor -> published
        if ($this->confirm('Publish OpenSearch templates?', false)) {
            $this->call('vendor:publish', [
                '--tag' => 'laravel-logger-opensearch',
            ]);
        }

        // 5. Publish Docker setup (optional)
        // Note: Docker scripts can be used directly from the package. Publishing is only needed if you want to customize them.
        if ($this->confirm('Publish Docker setup scripts?', false)) {
            $this->call('vendor:publish', [
                '--tag' => 'laravel-logger-docker',
            ]);
        }

        $this->newLine();
        $this->info('✅ Installation complete!');
        $this->newLine();
        $this->line('Next steps:');
        $this->line('  1. Configure your .env file (see INSTALLATION.md)');
        $this->line('  2. Set up OpenSearch: php artisan opensearch:test');
        $this->line('  3. Start logging: use Ermetix\LaravelLogger\Facades\LaravelLogger');

        return Command::SUCCESS;
    }

    protected function addLoggingChannels(): void
    {
        $loggingPath = config_path('logging.php');
        $channelsStub = file_get_contents(__DIR__.'/../../../stubs/logging-channels.stub');

        if (!file_exists($loggingPath)) {
            $this->warn('   ⚠️  config/logging.php not found, skipping...');
            return;
        }

        $content = file_get_contents($loggingPath);

        // Check if channels already exist
        if (str_contains($content, "Ermetix\\LaravelLogger\\Logging\\CreateOpenSearchLogger")) {
            if (!$this->option('force')) {
                $this->line('⚠️  Logging channels already configured. Use --force to overwrite.');
                return;
            }
            // Remove existing channels
            $content = preg_replace(
                "/\s*\/\/ Kafka.*?'opensearch' => \[.*?\],\s*/s",
                '',
                $content
            );
        }

        // Find the position to insert (before 'emergency' channel)
        $insertPosition = strrpos($content, "'emergency'");
        
        if ($insertPosition === false) {
            $this->warn('   ⚠️  Could not find insertion point in config/logging.php');
            return;
        }

        // Insert the channels
        $content = substr_replace($content, $channelsStub."\n\n        ", $insertPosition, 0);

        file_put_contents($loggingPath, $content);
        $this->info('   ✅ Logging channels added to config/logging.php');
    }

    protected function addMiddlewareConfiguration(): void
    {
        $appPath = base_path('bootstrap/app.php');

        if (!file_exists($appPath)) {
            $this->warn('   ⚠️  bootstrap/app.php not found, skipping...');
            return;
        }

        $content = file_get_contents($appPath);
        $middlewareStub = file_get_contents(__DIR__.'/../../../stubs/bootstrap-app.stub');
        $exceptionsStub = file_get_contents(__DIR__.'/../../../stubs/bootstrap-exceptions.stub');

        $alreadyConfigured = str_contains($content, "Ermetix\\LaravelLogger\\Http\\Middleware\\RequestId");

        if ($alreadyConfigured) {
            if (!$this->option('force')) {
                $this->warn('   ⚠️  Middleware already configured. Use --force to overwrite.');
                return;
            }
            // Remove existing package middleware and exceptions configuration
            // Match multiline blocks that contain Ermetix\LaravelLogger
            $content = preg_replace(
                "/\s*->withMiddleware\s*\(function\s*\([^)]*\)\s*:\s*void\s*\{[^}]*Ermetix\\\\LaravelLogger[^}]*\}\);\s*/s",
                '',
                $content
            );
            $content = preg_replace(
                "/\s*->withExceptions\s*\(function\s*\([^)]*\)\s*:\s*void\s*\{[^}]*Ermetix\\\\LaravelLogger[^}]*\}\);\s*/s",
                '',
                $content
            );
        }

        // Replace empty blocks by finding and replacing the entire block
        // Need to replace: ->withMiddleware(function (...) { // }) with the stub
        $replacedMiddleware = false;
        $replacedExceptions = false;

        // Find and replace entire middleware block
        if (preg_match('/->withMiddleware\s*\(function\s*\([^)]*\)\s*:\s*void\s*\{/', $content, $matches, PREG_OFFSET_CAPTURE)) {
            $blockStartPos = $matches[0][1]; // Start of ->withMiddleware
            $contentStartPos = $matches[0][1] + strlen($matches[0][0]); // After opening {
            
            // Find the matching closing brace
            $braceCount = 1;
            $pos = $contentStartPos;
            $closingBracePos = -1;
            while ($pos < strlen($content) && $braceCount > 0) {
                if ($content[$pos] === '{') $braceCount++;
                if ($content[$pos] === '}') {
                    $braceCount--;
                    if ($braceCount === 0) {
                        $closingBracePos = $pos;
                        break;
                    }
                }
                $pos++;
            }
            
            if ($closingBracePos > 0) {
                // Find the closing ); after the brace
                $pos = $closingBracePos + 1;
                while ($pos < strlen($content) && in_array($content[$pos], [' ', "\t", "\n", "\r"])) {
                    $pos++;
                }
                $blockEndPos = $pos;
                if ($pos < strlen($content) && $content[$pos] === ')') {
                    $blockEndPos = $pos + 1;
                    if ($pos + 1 < strlen($content) && $content[$pos + 1] === ';') {
                        $blockEndPos = $pos + 2;
                    }
                }
                
                // Extract block content (between { and })
                $blockContent = substr($content, $contentStartPos, $closingBracePos - $contentStartPos);
                $trimmed = trim($blockContent);
                
                // Check if block is empty (only whitespace and/or comments)
                if (empty($trimmed) || $trimmed === '//' || preg_match('/^\s*\/\/\s*$/', $trimmed)) {
                    // Replace entire block from ->withMiddleware to });
                    $before = substr($content, 0, $blockStartPos);
                    $after = substr($content, $blockEndPos);
                    $newBlock = trim($middlewareStub);
                    $content = $before . $newBlock . "\n    " . $after;
                    $replacedMiddleware = true;
                }
            }
        }

        // Same for exceptions
        if (preg_match('/->withExceptions\s*\(function\s*\([^)]*\)\s*:\s*void\s*\{/', $content, $matches, PREG_OFFSET_CAPTURE)) {
            $blockStartPos = $matches[0][1];
            $contentStartPos = $matches[0][1] + strlen($matches[0][0]);
            
            $braceCount = 1;
            $pos = $contentStartPos;
            $closingBracePos = -1;
            while ($pos < strlen($content) && $braceCount > 0) {
                if ($content[$pos] === '{') $braceCount++;
                if ($content[$pos] === '}') {
                    $braceCount--;
                    if ($braceCount === 0) {
                        $closingBracePos = $pos;
                        break;
                    }
                }
                $pos++;
            }
            
            if ($closingBracePos > 0) {
                $pos = $closingBracePos + 1;
                while ($pos < strlen($content) && in_array($content[$pos], [' ', "\t", "\n", "\r"])) {
                    $pos++;
                }
                $blockEndPos = $pos;
                if ($pos < strlen($content) && $content[$pos] === ')') {
                    $blockEndPos = $pos + 1;
                    if ($pos + 1 < strlen($content) && $content[$pos + 1] === ';') {
                        $blockEndPos = $pos + 2;
                    }
                }
                
                $blockContent = substr($content, $contentStartPos, $closingBracePos - $contentStartPos);
                $trimmed = trim($blockContent);
                
                if (empty($trimmed) || $trimmed === '//' || preg_match('/^\s*\/\/\s*$/', $trimmed)) {
                    $before = substr($content, 0, $blockStartPos);
                    $after = substr($content, $blockEndPos);
                    $newBlock = trim($exceptionsStub);
                    $content = $before . $newBlock . "\n    " . $after;
                    $replacedExceptions = true;
                }
            }
        }

        // If we replaced empty blocks, verify both are configured
        if ($replacedMiddleware || $replacedExceptions) {
            // Check if both are now configured
            $hasMiddleware = str_contains($content, "Ermetix\\LaravelLogger\\Http\\Middleware\\RequestId");
            $hasExceptions = str_contains($content, "Ermetix\\LaravelLogger\\Support\\Logging\\Objects\\ErrorLogObject");

            // If one was replaced but the other still needs to be added
            if ($replacedMiddleware && !$hasExceptions) {
                // Add exceptions block before ->create()
                $insertPosition = strrpos($content, '->create()');
                if ($insertPosition !== false) {
                    $content = substr_replace($content, $exceptionsStub."\n    ", $insertPosition, 0);
                }
            } elseif ($replacedExceptions && !$hasMiddleware) {
                // Add middleware block before ->create()
                $insertPosition = strrpos($content, '->create()');
                if ($insertPosition !== false) {
                    $content = substr_replace($content, $middlewareStub."\n    ", $insertPosition, 0);
                }
            }

            file_put_contents($appPath, $content);
            $this->info('   ✅ Middleware and exception handling configured in bootstrap/app.php');
            return;
        }

        // If no empty blocks found, check if blocks exist with content
        $hasMiddlewareBlock = preg_match("/->withMiddleware\s*\(function\s*\([^)]*\)\s*:\s*void\s*\{[^}]*\}\);/s", $content);
        $hasExceptionsBlock = preg_match("/->withExceptions\s*\(function\s*\([^)]*\)\s*:\s*void\s*\{[^}]*\}\);/s", $content);

        // If blocks exist but don't contain our code, insert before ->create()
        if (($hasMiddlewareBlock || $hasExceptionsBlock) && !$alreadyConfigured) {
            $insertPosition = strrpos($content, '->create()');
            
            if ($insertPosition === false) {
                $this->warn('   ⚠️  Could not find insertion point in bootstrap/app.php');
                return;
            }

            // Insert both middleware and exceptions configuration
            $configuration = $middlewareStub."\n    ".$exceptionsStub."\n    ";
            $content = substr_replace($content, $configuration, $insertPosition, 0);

            file_put_contents($appPath, $content);
            $this->info('   ✅ Middleware and exception handling added to bootstrap/app.php');
            return;
        }

        // Last resort: find the position to insert (before ->create())
        $insertPosition = strrpos($content, '->create()');
        
        if ($insertPosition === false) {
            // Fallback for non-standard bootstrap/app.php (e.g. Orchestra Testbench workbench):
            // add a safe comment block that contains the required class references.
            $configuration = "\n\n/*\n"
                . " |--------------------------------------------------------------------------\n"
                . " | Laravel Logger bootstrap configuration\n"
                . " |--------------------------------------------------------------------------\n"
                . " | Your bootstrap/app.php does not match the expected Laravel 11/12 structure.\n"
                . " | Please copy the following snippets into your Application::configure() chain.\n"
                . " |\n"
                . " | Middleware:\n"
                . " |   \\Ermetix\\LaravelLogger\\Http\\Middleware\\RequestId::class\n"
                . " |   \\Ermetix\\LaravelLogger\\Http\\Middleware\\ApiAccessLog::class\n"
                . " |   \\Ermetix\\LaravelLogger\\Http\\Middleware\\FlushDeferredLogs::class\n"
                . " |\n"
                . " */\n\n";

            $returnPos = strrpos($content, 'return $app;');
            if ($returnPos !== false) {
                $content = substr_replace($content, $configuration, $returnPos, 0);
            } else {
                $content .= $configuration;
            }

            file_put_contents($appPath, $content);
            $this->info('   ✅ Middleware and exception handling added to bootstrap/app.php');
            return;
        }

        // Insert both middleware and exceptions configuration
        $configuration = $middlewareStub."\n    ".$exceptionsStub."\n    ";
        $content = substr_replace($content, $configuration, $insertPosition, 0);

        file_put_contents($appPath, $content);
        $this->info('   ✅ Middleware and exception handling added to bootstrap/app.php');
    }
}
