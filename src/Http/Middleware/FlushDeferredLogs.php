<?php

namespace Ermetix\LaravelLogger\Http\Middleware;

use Ermetix\LaravelLogger\Support\Logging\DeferredLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware terminable that flushes all deferred logs after the response is sent.
 * This ensures logs are written without blocking the response.
 */
class FlushDeferredLogs
{
    public function __construct(
        private readonly DeferredLogger $deferredLogger,
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            // In case of exception, flush logs before re-throwing
            $this->deferredLogger->flush();
            throw $e;
        }
        
        return $response;
    }

    /**
     * Handle tasks after the response has been sent to the browser.
     */
    public function terminate(Request $request, Response $response): void
    {
        // Flush all deferred logs after the response is sent
        // This is called even if there was an exception (if response was created)
        try {
            $this->deferredLogger->flush();
        } catch (\Throwable $e) {
            // Ignore errors during termination to avoid breaking the response
        }
    }
}
