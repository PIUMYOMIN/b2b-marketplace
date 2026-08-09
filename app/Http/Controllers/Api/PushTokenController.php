<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PushToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PushTokenController extends Controller
{
    /** POST /push-tokens */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:512', 'regex:/^ExponentPushToken\\[.+\\]$/'],
            'provider' => 'required|string|in:expo',
            'platform' => 'nullable|string|in:ios,android,web',
            'device_name' => 'nullable|string|max:255',
        ]);

        $user = $request->user();
        $platform = $validated['platform'] ?? null;

        // One active Expo token per user+platform (avoids 20+ stale tokens per message).
        PushToken::query()
            ->where('user_id', $user->id)
            ->where('provider', 'expo')
            ->when(
                $platform !== null,
                fn ($q) => $q->where('platform', $platform),
                fn ($q) => $q->whereNull('platform'),
            )
            ->where('token', '!=', $validated['token'])
            ->delete();

        PushToken::updateOrCreate(
            ['token' => $validated['token']],
            [
                'user_id' => $user->id,
                'provider' => $validated['provider'],
                'platform' => $platform,
                'device_name' => $validated['device_name'] ?? null,
                'last_used_at' => now(),
            ]
        );

        Log::info('Expo push token registered', [
            'user_id' => $user->id,
            'platform' => $platform,
            'token_prefix' => substr($validated['token'], 0, 32),
        ]);

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
