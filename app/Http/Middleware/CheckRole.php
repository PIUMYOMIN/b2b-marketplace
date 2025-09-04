<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated
        if (!$request->user()) {
            return response()->json([
                'message' => 'Unauthenticated',
                'success' => false
            ], 401);
        }

        // Check if user has the required role using Spatie's hasRole method
        if (!$request->user()->hasRole($role)) {
            return response()->json([
                'message' => 'Forbidden - ' . ucfirst($role) . ' access required',
                'success' => false
            ], 403);
        }

        return $next($request);
    }
}