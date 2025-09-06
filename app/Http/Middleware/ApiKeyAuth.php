<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-API-Key') ?? $request->query('api_key');
        $validApiKey = config('app.api_key', env('API_KEY'));

        if (!$validApiKey) {
            return response()->json([
                'message' => 'API key validation not configured',
            ], 500);
        }

        if (!$apiKey || $apiKey !== $validApiKey) {
            return response()->json([
                'message' => 'Invalid or missing API key',
            ], 401);
        }

        return $next($request);
    }
}
