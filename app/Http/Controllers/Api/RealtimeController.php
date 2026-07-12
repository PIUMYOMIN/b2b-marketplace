<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class RealtimeController extends Controller
{
    /** GET /realtime/config — WebSocket connection details for mobile/web clients */
    public function config(Request $request)
    {
        $driver = config('broadcasting.default');
        $enabled = $driver === 'reverb';
        $reverb = config('broadcasting.connections.reverb');

        return response()->json([
            'success' => true,
            'data' => [
                'enabled' => $enabled,
                'driver' => $driver,
                'key' => $enabled ? ($reverb['key'] ?? null) : null,
                'auth_endpoint' => url('/api/v1/broadcasting/auth'),
                'host' => $enabled ? ($reverb['options']['host'] ?? null) : null,
                'port' => $enabled ? (int) ($reverb['options']['port'] ?? 443) : null,
                'scheme' => $enabled ? ($reverb['options']['scheme'] ?? 'https') : null,
                'channels' => [
                    'conversation' => 'private-conversation.{id}',
                ],
                'events' => [
                    'message_sent' => 'message.sent',
                    'typing' => 'conversation.typing',
                ],
            ],
        ]);
    }
}
