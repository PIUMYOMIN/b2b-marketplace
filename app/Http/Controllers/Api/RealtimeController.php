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
        $enabled = in_array($driver, ['reverb', 'pusher'], true);

        $payload = [
            'enabled' => false,
            'driver' => $driver,
            'key' => null,
            'cluster' => null,
            'auth_endpoint' => url('/api/v1/broadcasting/auth'),
            'host' => null,
            'port' => 443,
            'scheme' => 'https',
            'channels' => [
                'conversation' => 'private-conversation.{id}',
            ],
            'events' => [
                'message_sent' => 'message.sent',
                'typing' => 'conversation.typing',
            ],
        ];

        if ($driver === 'pusher') {
            $pusher = config('broadcasting.connections.pusher');
            $key = $pusher['key'] ?? null;
            $cluster = $pusher['options']['cluster'] ?? null;

            $payload['enabled'] = $enabled && !empty($key) && !empty($cluster);
            $payload['key'] = $key;
            $payload['cluster'] = $cluster;
            $payload['scheme'] = $pusher['options']['scheme'] ?? 'https';
            $payload['port'] = (int) ($pusher['options']['port'] ?? 443);
        } elseif ($driver === 'reverb') {
            $reverb = config('broadcasting.connections.reverb');
            $key = $reverb['key'] ?? null;

            $payload['enabled'] = $enabled && !empty($key);
            $payload['key'] = $key;
            $payload['host'] = $reverb['options']['host'] ?? null;
            $payload['port'] = (int) ($reverb['options']['port'] ?? 443);
            $payload['scheme'] = $reverb['options']['scheme'] ?? 'https';
        }

        return response()->json([
            'success' => true,
            'data' => $payload,
        ]);
    }
}
