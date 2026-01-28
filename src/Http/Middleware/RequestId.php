<?php

namespace Ermetix\LaravelLogger\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = (string) ($request->header('X-Request-Id') ?: $request->header('X-Request-ID') ?: '');

        if ($requestId === '') {
            $requestId = (string) Str::uuid();
        }

        // Generate trace_id - use same value as request_id to keep them linked
        // If trace_id is provided in header, use it; otherwise use request_id
        $traceId = (string) ($request->header('X-Trace-Id') ?: $request->header('X-Trace-ID') ?: $request->header('traceparent') ?: '');
        
        if ($traceId === '') {
            // Use request_id as trace_id to keep them linked across all logs
            $traceId = $requestId;
        }

        // Accessible via Context and automatically injected into log "extra"
        // by Illuminate\Log\Context\ContextLogProcessor (we add it to our custom loggers).
        Context::add('request_id', $requestId);
        Context::add('trace_id', $traceId);

        // Also available to application code: request()->attributes->get('request_id')
        $request->attributes->set('request_id', $requestId);
        $request->attributes->set('trace_id', $traceId);

        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $next($request);

        $response->headers->set('X-Request-Id', $requestId);
        $response->headers->set('X-Trace-Id', $traceId);

        return $response;
    }
}
