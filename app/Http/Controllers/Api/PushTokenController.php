<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PushToken;
use Illuminate\Http\Request;

class PushTokenController extends Controller
{
    /** POST /push-tokens */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'token' => 'required|string|max:255',
            'provider' => 'required|string|in:expo',
            'platform' => 'nullable|string|in:ios,android,web',
            'device_name' => 'nullable|string|max:255',
        ]);

        $user = $request->user();

        PushToken::updateOrCreate(
            ['token' => $validated['token']],
            [
                'user_id' => $user->id,
                'provider' => $validated['provider'],
                'platform' => $validated['platform'] ?? null,
                'device_name' => $validated['device_name'] ?? null,
                'last_used_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Push token registered.',
        ]);
    }

    /** DELETE /push-tokens/{token} */
    public function destroy(Request $request, string $token)
    {
        $decodedToken = urldecode($token);

        PushToken::query()
            ->where('user_id', $request->user()->id)
            ->where('token', $decodedToken)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Push token removed.',
        ]);
    }
}
