<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyMiddleware
{
    /**
     * Handle an incoming request.
     *
     * Checks the X-API-KEY header or query parameters (api_key or key) against
     * the environment variable API_ROUTE_KEY. Returns 401 when missing or invalid.
     */
    public function handle(Request $request, Closure $next)
    {
        $expected = env('API_ROUTE_KEY');

        $provided = $request->header('X-API-KEY') ?? $request->query('api_key') ?? $request->query('key');

        // Require API_ROUTE_KEY to be set and match the provided value.
        if (empty($expected) || empty($provided) || !hash_equals((string)$expected, (string)$provided)) {
            return response()->json(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
