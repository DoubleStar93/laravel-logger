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
        // Get trace_id from headers (for distributed tracing)
        // trace_id should remain the same across all services in a distributed trace
        $traceId = (string) ($request->header('X-Trace-Id') ?: $request->header('X-Trace-ID') ?: $request->header('traceparent') ?: '');
        
        // If no trace_id provided, generate a new one (this is the start of a new trace)
        if ($traceId === '') {
            $traceId = (string) Str::uuid();
        }

        // Get request_id from headers (unique per request/service)
        // request_id should be unique for each request, even if trace_id is the same
        $requestId = (string) ($request->header('X-Request-Id') ?: $request->header('X-Request-ID') ?: '');

        // If no request_id provided, generate a new one
        // This ensures each service/request has its own unique request_id
        if ($requestId === '') {
            $requestId = (string) Str::uuid();
        }

        // Get parent_request_id if present (from calling service)
        $parentRequestId = (string) ($request->header('X-Parent-Request-Id') ?: $request->header('X-Parent-Request-ID') ?: '');

        // Accessible via Context and automatically injected into log "extra"
        // by Illuminate\Log\Context\ContextLogProcessor (we add it to our custom loggers).
        Context::add('request_id', $requestId);
        Context::add('trace_id', $traceId);
        if ($parentRequestId !== '') {
            Context::add('parent_request_id', $parentRequestId);
        }

        // Also available to application code: request()->attributes->get('request_id')
        $request->attributes->set('request_id', $requestId);
        $request->attributes->set('trace_id', $traceId);
        if ($parentRequestId !== '') {
            $request->attributes->set('parent_request_id', $parentRequestId);
        }

        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $next($request);

        // Always return the current request_id and trace_id in response headers
        $response->headers->set('X-Request-Id', $requestId);
        $response->headers->set('X-Trace-Id', $traceId);

        return $response;
    }
}
