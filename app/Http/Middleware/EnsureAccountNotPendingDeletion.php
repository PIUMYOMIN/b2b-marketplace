<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountNotPendingDeletion
{
    /**
     * Block normal API usage while an account is in the pending-deletion grace period.
     * Recovery-related routes remain accessible.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->canRecoverFromPendingDeletion()) {
            if (
                $request->is('api/auth/logout', 'api/auth/me')
                || $request->is('api/users/profile/cancel-deletion')
            ) {
                return $next($request);
            }

            return response()->json([
                'success' => false,
                'message' => __('messages.users.deletion_recovery_required'),
                'data' => [
                    'account_pending_deletion' => true,
                    'deletion_requested_at' => $user->deletion_requested_at,
                    'deletion_scheduled_at' => $user->deletionScheduledAt(),
                ],
            ], 403);
        }

        return $next($request);
    }
}
