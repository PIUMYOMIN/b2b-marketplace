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
}
