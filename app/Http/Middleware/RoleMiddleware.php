<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, $role, $permission = null)
    {
        try {
            // Check if user is authenticated
            if (!$request->user()) {
                return response()->json([
                    'message' => 'Unauthenticated',
                    'success' => false
                ], 401);
            }

            // Check role using Spatie
            if (!$request->user()->hasRole($role)) {
                return response()->json([
                    'message' => 'Forbidden - Insufficient permissions',
                    'success' => false
                ], 403);
            }

            // Check permission if provided
            if ($permission !== null && !$request->user()->can($permission)) {
                return response()->json([
                    'message' => 'Forbidden - Missing permission: ' . $permission,
                    'success' => false
                ], 403);
            }

            return $next($request);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Internal Server Error in role middleware',
                'error' => $e->getMessage(),
                'success' => false
            ], 500);
        }
    }
}