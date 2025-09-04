<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user()) {
            return response()->json([
                'message' => 'Unauthenticated',
                'success' => false
            ], 401);
        }

        // Check both Spatie role and type field as fallback
        $isAdmin = $request->user()->hasRole('admin') || $request->user()->type === 'admin';
        
        if (!$isAdmin) {
            return response()->json([
                'message' => 'Forbidden - Admin access required',
                'success' => false
            ], 403);
        }

        return $next($request);
    }
}