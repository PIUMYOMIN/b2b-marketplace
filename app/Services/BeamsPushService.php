<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Pusher\PushNotifications\PushNotifications;

class BeamsPushService
{
    private ?PushNotifications $client = null;

    public function isConfigured(): bool
    {
        return !empty(config('services.beams.instance_id'))
            && !empty(config('services.beams.secret_key'));
    }

    public function client(): PushNotifications
    {
        if ($this->client !== null) {
            return $this->client;
        }

        $this->client = new PushNotifications([
            'instanceId' => (string) config('services.beams.instance_id'),
            'secretKey' => (string) config('services.beams.secret_key'),
        ]);

        return $this->client;
    }

    /**
     * @return array<string, mixed>
     */
    public function generateUserToken(string|int $userId): array
    {
        return $this->client()->generateToken((string) $userId);
    }

    /**
     * @param  array<int, string>  $interests
     * @param  array<string, mixed>  $payload
     */
    public function publishToInterests(array $interests, array $payload): void
    {
        if (!$this->isConfigured() || $interests === []) {
            return;
        }

        try {
            $this->client()->publishToInterests($interests, $payload);
        } catch (\Throwable $exception) {
            Log::error('Pusher Beams publishToInterests failed', [
                'interests' => $interests,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Interest name the mobile app always subscribes to for this user.
     * Used as a delivery fallback when authenticated-user devices are missing.
     */
    public function userInterest(string|int $userId): string
    {
        return 'user-'.((string) $userId);
    }

    /**
     * @param  array<int, string|int>  $userIds
     * @param  array<string, mixed>  $payload
     */
    public function publishToUsers(array $userIds, array $payload): void
    {
        if (!$this->isConfigured() || $userIds === []) {
            return;
        }

        $users = array_values(array_map('strval', $userIds));

        // Primary delivery: per-user interests. Authenticated-user publish is
        // unreliable while setUserId hangs on some release APKs (users_no_devices).
        // Include debug-u-{id} so currently installed builds (already subscribed)
        // receive pushes before the next APK that also joins user-{id}.
        $interests = [];
        foreach ($users as $id) {
            $interests[] = $this->userInterest($id);
            $interests[] = 'debug-u-'.$id;
        }
        $interests = array_values(array_unique($interests));

        try {
            $response = $this->client()->publishToInterests($interests, $payload);
            $publishId = is_object($response) && isset($response->publishId)
                ? (string) $response->publishId
                : null;

            Log::info('Pusher Beams publishToInterests accepted', [
                'users' => $users,
                'interests' => $interests,
                'publish_id' => $publishId,
            ]);
        } catch (\Throwable $exception) {
            Log::error('Pusher Beams publishToInterests failed', [
                'users' => $users,
                'interests' => $interests,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Send a push using the same message shape as ExpoPushService.
     *
     * @param  array<string, mixed>  $message
     */
    public function sendToUser(int $userId, array $message): void
    {
        if (!$this->isConfigured()) {
            return;
        }

        $title = (string) ($message['title'] ?? 'Pyonea');
        $body = (string) ($message['body'] ?? '');
        $data = is_array($message['data'] ?? null) ? $message['data'] : [];

        if ($title === '' && $body === '') {
            return;
        }

        if (!empty($message['channelId'])) {
            $data['channelId'] = (string) $message['channelId'];
        }

        $this->publishToUsers([$userId], $this->buildFcmPayload($title, $body, $data));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function buildFcmPayload(string $title, string $body, array $data = []): array
    {
        $channelId = is_string($data['channelId'] ?? null) && $data['channelId'] !== ''
            ? (string) $data['channelId']
            : 'messages';

        return [
            'fcm' => [
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                    'android_channel_id' => $channelId,
                ],
                'data' => array_map('strval', $data),
                'android' => [
                    'priority' => 'high',
                    'notification' => [
                        'channel_id' => $channelId,
                        'sound' => 'default',
                    ],
                ],
            ],
            'apns' => [
                'aps' => [
                    'alert' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'sound' => 'default',
                ],
                'data' => $data,
            ],
        ];
    }
}
