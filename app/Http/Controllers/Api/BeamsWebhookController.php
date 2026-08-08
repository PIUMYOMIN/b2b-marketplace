<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receives Pusher Beams dashboard webhooks (PublishToUsersAttempt, etc.)
 * so we can inspect delivery without webhook.site.
 */
class BeamsWebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        $eventType = (string) $request->header('Webhook-Event-Type', '');
        $payload = $request->all();

        Log::info('Pusher Beams webhook received', [
            'event_type' => $eventType,
            'payload' => $payload['payload'] ?? $payload,
            'metadata' => $payload['metadata'] ?? null,
        ]);

        return response()->json(['ok' => true], 200);
    }
}
