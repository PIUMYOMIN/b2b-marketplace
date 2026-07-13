<?php

namespace App\Services;

use App\Models\PushToken;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExpoPushService
{
    private const API_URL = 'https://exp.host/--/api/v2/push/send';

    /**
     * @param  Collection<int, PushToken>|array<int, PushToken>  $tokens
     * @param  array<string, mixed>  $message
     */
    public function sendToTokens(Collection|array $tokens, array $message): void
    {
        $tokenModels = $tokens instanceof Collection ? $tokens : collect($tokens);
        if ($tokenModels->isEmpty()) {
            return;
        }

        $payloads = $tokenModels
            ->pluck('token')
            ->filter(fn (string $token) => str_starts_with($token, 'ExponentPushToken['))
            ->unique()
            ->values()
            ->map(fn (string $token) => $this->buildPayload($token, $message))
            ->all();

        if ($payloads === []) {
            Log::info('Expo push skipped: no valid Expo push tokens in selection.');

            return;
        }

        foreach (array_chunk($payloads, 100) as $chunk) {
            $this->dispatchChunk($chunk, $tokenModels);
        }
    }

    public function sendToUser(int $userId, array $message): void
    {
        $tokens = PushToken::query()
            ->where('user_id', $userId)
            ->where('provider', 'expo')
            ->get();

        if ($tokens->isEmpty()) {
            Log::info('Expo push skipped: no registered tokens for user.', [
                'user_id' => $userId,
                'type' => $message['data']['type'] ?? null,
            ]);

            return;
        }

        Log::info('Expo push dispatching.', [
            'user_id' => $userId,
            'token_count' => $tokens->count(),
            'type' => $message['data']['type'] ?? null,
        ]);

        $this->sendToTokens($tokens, $message);
    }

    /**
     * @param  array<int, array<string, mixed>>  $payloads
     * @param  Collection<int, PushToken>  $tokenModels
     */
    private function dispatchChunk(array $payloads, Collection $tokenModels): void
    {
        try {
            $response = Http::timeout(10)
                ->acceptJson()
                ->post(self::API_URL, $payloads);

            if (!$response->successful()) {
                Log::warning('Expo push request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return;
            }

            $results = $response->json('data');
            if (!is_array($results)) {
                Log::warning('Expo push response missing data array', [
                    'body' => $response->body(),
                ]);

                return;
            }

            Log::info('Expo push response received.', [
                'results' => $results,
            ]);

            foreach ($results as $index => $result) {
                if (!is_array($result)) {
                    continue;
                }

                $status = $result['status'] ?? null;
                if ($status !== 'error') {
                    continue;
                }

                $details = is_array($result['details'] ?? null) ? $result['details'] : [];
                $error = $details['error'] ?? null;
                if ($error === 'InvalidCredentials') {
                    Log::error('Expo push FCM credentials missing or invalid. Upload FCM V1 service account to Expo project credentials.', [
                        'message' => $result['message'] ?? 'Unknown error',
                        'details' => $details,
                    ]);
                    continue;
                }

                if ($error !== 'DeviceNotRegistered') {
                    Log::warning('Expo push delivery error', [
                        'message' => $result['message'] ?? 'Unknown error',
                        'details' => $details,
                    ]);
                    continue;
                }

                $tokenValue = $payloads[$index]['to'] ?? null;
                if (!is_string($tokenValue) || $tokenValue === '') {
                    continue;
                }

                PushToken::query()->where('token', $tokenValue)->delete();
            }

            PushToken::query()
                ->whereIn('id', $tokenModels->pluck('id'))
                ->update(['last_used_at' => now()]);
        } catch (\Throwable $e) {
            Log::warning('Expo push dispatch failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>
     */
    private function buildPayload(string $token, array $message): array
    {
        $payload = [
            'to' => $token,
            'title' => (string) ($message['title'] ?? 'Pyonea'),
            'body' => (string) ($message['body'] ?? ''),
            'sound' => $message['sound'] ?? 'default',
            'priority' => $message['priority'] ?? 'high',
            'data' => is_array($message['data'] ?? null) ? $message['data'] : [],
        ];

        if (!empty($message['channelId'])) {
            $payload['channelId'] = (string) $message['channelId'];
        }

        if (!empty($message['badge'])) {
            $payload['badge'] = (int) $message['badge'];
        }

        return $payload;
    }
}
