<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BeamsPushService;
use Illuminate\Http\Request;

class BeamsAuthController extends Controller
{
    public function __construct(private readonly BeamsPushService $beams) {}

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

        return response()->json([
            'success' => true,
            'data' => $tokenPayload,
        ]);
    }
}
