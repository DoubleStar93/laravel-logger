<?php

namespace Ermetix\LaravelLogger\Http\Middleware;

use Ermetix\LaravelLogger\Facades\LaravelLogger as Log;
use Ermetix\LaravelLogger\Support\Config\ConfigReader;
use Ermetix\LaravelLogger\Support\Logging\Objects\ApiLogObject;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

class ApiAccessLog
{
    public function handle(Request $request, Closure $next): Response
    {
        $start = hrtime(true);

        // Capture request body before processing
        $requestBody = $this->getRequestBody($request);
        $requestSizeBytes = $requestBody !== null ? strlen($requestBody) : null;

        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $next($request);

        $durationMs = (hrtime(true) - $start) / 1_000_000;

        $route = $request->route();

        // Capture response body
        $responseBody = $this->getResponseBody($response);

        // Detect authentication method
        $authenticationMethod = $this->detectAuthenticationMethod($request);

        // Detect API version from path (e.g., /api/v1/users -> v1)
        $apiVersion = $this->detectApiVersion($request);

        // Use typed LogObject instead of generic context array
        // Defer logging to end of request to avoid blocking the response
        Log::api(
            new ApiLogObject(
                message: 'api_access',
                method: $request->getMethod(),
                path: $request->getPathInfo(),
                routeName: $route?->getName(),
                status: $response->getStatusCode(),
                durationMs: (int) round($durationMs),
                ip: $request->ip(),
                userId: Auth::id() ? (string) Auth::id() : null,
                userAgent: $request->userAgent(),
                referer: $request->header('referer'),
                queryString: $request->getQueryString(),
                requestSizeBytes: $requestSizeBytes,
                responseSizeBytes: $responseBody !== null ? strlen($responseBody) : null,
                authenticationMethod: $authenticationMethod,
                apiVersion: $apiVersion,
                requestBody: $requestBody,
                responseBody: $responseBody,
                requestHeaders: $this->getRequestHeaders($request),
                responseHeaders: $this->getResponseHeaders($response),
                level: 'info',
            ),
            defer: true, // Accumulate in memory, write at end of request
        );

        return $response;
    }

    /**
     * Get request body content, limiting size to prevent huge logs.
     * Checks Content-Length header first to avoid loading very large bodies into memory.
     * This prevents memory exhaustion with large file uploads.
     */
    private function getRequestBody(Request $request): ?string
    {
        // Skip for GET/HEAD requests
        if (in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'])) {
            return null;
        }

        try {
            $maxSize = ConfigReader::getInt('limits.max_request_body_size', 10240);
            
            // Check Content-Length header to avoid loading very large bodies
            // If Content-Length is much larger than our limit, skip reading the body
            $contentLength = $request->header('Content-Length');
            if ($contentLength !== null && is_numeric($contentLength)) {
                $length = (int) $contentLength;
                // If body is more than 2x our limit, don't read it at all to prevent memory issues
                if ($length > ($maxSize * 2)) {
                    return null;
                }
            }
            
            // Get content (Laravel may have already loaded it, but we've checked size above)
            $content = $request->getContent();
            
            // Limit size to configured maximum to prevent huge logs
            if ($content !== null && strlen($content) > $maxSize) {
                return substr($content, 0, $maxSize).'...[truncated]';
            }

            return $content ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Get response body content, limiting size to prevent huge logs.
     * Checks Content-Length header first to avoid loading very large responses into memory.
     */
    private function getResponseBody(Response $response): ?string
    {
        try {
            $maxSize = ConfigReader::getInt('limits.max_response_body_size', 10240);
            
            // Check Content-Length header to avoid loading very large responses
            $contentLength = $response->headers->get('Content-Length');
            if ($contentLength !== null && is_numeric($contentLength)) {
                $length = (int) $contentLength;
                // If response is more than 2x our limit, don't read it at all to prevent memory issues
                if ($length > ($maxSize * 2)) {
                    return null;
                }
            }
            
            $content = $response->getContent();
            
            // Limit size to configured maximum to prevent huge logs
            if ($content !== null && strlen($content) > $maxSize) {
                return substr($content, 0, $maxSize).'...[truncated]';
            }

            return $content ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Get request headers (excluding sensitive ones).
     */
    private function getRequestHeaders(Request $request): ?array
    {
        $headers = [];
        $sensitive = ['authorization', 'cookie', 'x-api-key', 'x-auth-token'];

        foreach ($request->headers->all() as $key => $values) {
            $lowerKey = strtolower($key);
            if (in_array($lowerKey, $sensitive)) {
                $headers[$key] = '[redacted]';
            } else {
                $headers[$key] = $values[0] ?? null;
            }
        }

        return !empty($headers) ? $headers : null;
    }

    /**
     * Get response headers.
     */
    private function getResponseHeaders(Response $response): ?array
    {
        $headers = [];

        foreach ($response->headers->all() as $key => $values) {
            $headers[$key] = $values[0] ?? null;
        }

        return !empty($headers) ? $headers : null;
    }

    /**
     * Detect authentication method from request.
     */
    private function detectAuthenticationMethod(Request $request): ?string
    {
        // Check for Bearer token
        if ($request->bearerToken()) {
            return 'bearer';
        }

        // Check for API key in headers
        if ($request->header('X-API-Key') || $request->header('X-Api-Key')) {
            return 'api_key';
        }

        // Check for session-based auth
        if (Auth::check()) {
            // If authenticated via session
            if ($request->hasSession() && $request->session()->has('_token')) {
                return 'session';
            }
            // If authenticated via Sanctum/Passport token
            if ($request->user() && method_exists($request->user(), 'currentAccessToken')) {
                return 'token';
            }
            return 'authenticated'; // Generic authenticated state
        }

        // Check for basic auth
        if ($request->header('Authorization') && str_starts_with($request->header('Authorization'), 'Basic ')) {
            return 'basic';
        }

        return null; // No authentication detected
    }

    /**
     * Detect API version from request path.
     * Supports patterns like: /api/v1/users, /v2/users, /api/v3.1/users
     */
    private function detectApiVersion(Request $request): ?string
    {
        $path = $request->getPathInfo();

        // Pattern: /api/v1/... or /api/v2.1/...
        if (preg_match('#^/api/v(\d+(?:\.\d+)?)/#', $path, $matches)) {
            return 'v' . $matches[1];
        }

        // Pattern: /v1/... or /v2.1/...
        if (preg_match('#^/v(\d+(?:\.\d+)?)/#', $path, $matches)) {
            return 'v' . $matches[1];
        }

        // Check X-API-Version header
        $versionHeader = $request->header('X-API-Version') ?? $request->header('API-Version');
        if ($versionHeader) {
            return $versionHeader;
        }

        return null;
    }
}
