<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        if (!$request->user()) {
            return response()->json([
                'message' => 'Unauthenticated',
            ], 401);
        }

        // Check if user has the required role
        // Note: This assumes you have a 'role' column in your users table
        // You might want to implement a more sophisticated role system
        if (!$request->user()->hasRole($role)) {
            return response()->json([
                'message' => 'Insufficient permissions',
            ], 403);
        }

        return $next($request);
    }
}
