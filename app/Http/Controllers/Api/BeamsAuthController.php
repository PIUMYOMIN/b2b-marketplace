<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BeamsPushService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BeamsAuthController extends Controller
{
    public function __construct(private readonly BeamsPushService $beams) {}

    /**
     * GET /beams/auth — Beams Android SDK BeamsTokenProvider format.
     * Must return top-level {"token":"..."} (not wrapped).
     */
    public function show(Request $request)
    {
        if (!$this->beams->isConfigured()) {
            return response()->json([
                'error' => 'Pusher Beams is not configured on this server.',
            ], 503);
        }

        $user = $request->user();
        $queryUserId = (string) $request->query('user_id', '');
        if ($queryUserId !== '' && $queryUserId !== (string) $user->id) {
            return response()->json(['error' => 'Inconsistent request'], 401);
        }

        $tokenPayload = $this->beams->generateUserToken($user->id);

        Log::info('Beams auth token issued', [
            'user_id' => $user->id,
            'method' => 'GET',
            'platform' => $request->header('X-Pyonea-Client') ?: $request->userAgent(),
        ]);

        return response()->json($tokenPayload);
    }

    /** POST /beams/auth — Beams authenticated-user token for the signed-in user */
    public function store(Request $request)
    {
        if (!$this->beams->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Pusher Beams is not configured on this server.',
            ], 503);
        }

        $user = $request->user();
        $tokenPayload = $this->beams->generateUserToken($user->id);

        Log::info('Beams auth token issued', [
            'user_id' => $user->id,
            'method' => 'POST',
            'platform' => $request->header('X-Pyonea-Client') ?: $request->userAgent(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $tokenPayload,
        ]);
    }

    /**
     * POST /beams/client-status — app-side Beams registration breadcrumbs
     * (visible without adb when release APKs fail silently).
     */
    public function clientStatus(Request $request)
    {
        $data = $request->validate([
            'status' => 'required|string|max:64',
            'detail' => 'nullable|string|max:2000',
            'provider' => 'nullable|string|max:32',
            'has_instance_id' => 'nullable|boolean',
            'platform' => 'nullable|string|max:32',
        ]);

        Log::info('Beams client status', [
            'user_id' => $request->user()->id,
            'status' => $data['status'],
            'detail' => $data['detail'] ?? null,
            'provider' => $data['provider'] ?? null,
            'has_instance_id' => $data['has_instance_id'] ?? null,
            'platform' => $data['platform'] ?? $request->userAgent(),
        ]);

        return response()->json(['ok' => true]);
    }
}
