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

        // Accessible via Context and automatically injected into log "extra"
        // by Illuminate\Log\Context\ContextLogProcessor (we add it to our custom loggers).
        Context::add('request_id', $requestId);

        // Also available to application code: request()->attributes->get('request_id')
        $request->attributes->set('request_id', $requestId);

        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $next($request);

        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
