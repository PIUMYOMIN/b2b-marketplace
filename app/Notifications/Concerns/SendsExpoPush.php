<?php

namespace App\Notifications\Concerns;

use App\Notifications\Channels\BeamsPushChannel;
use App\Notifications\Channels\ExpoPushChannel;
use App\Services\BeamsPushService;

trait SendsExpoPush
{
    /** @return array<int, class-string> */
    protected function mobilePushChannels(bool $shouldSend): array
    {
        if (!$shouldSend) {
            return [];
        }

        $channels = [ExpoPushChannel::class];

        if (config('services.beams.enabled', false) && app(BeamsPushService::class)->isConfigured()) {
            $channels[] = BeamsPushChannel::class;
        }

        return $channels;
    }

    /** @return array<string, mixed> */
    protected function notificationPreferences(object $user): array
    {
        $prefs = $user->notification_preferences ?? [];

        if (is_string($prefs)) {
            $prefs = json_decode($prefs, true) ?: [];
        }

        return is_array($prefs) ? $prefs : [];
    }

    protected function shouldSendOrderPush(object $user, ?string $specificPreferenceKey = null): bool
    {
        $prefs = $this->notificationPreferences($user);

        if (($prefs['order_notifications'] ?? true) === false) {
            return false;
        }

        if ($specificPreferenceKey !== null) {
            return (bool) (
                $prefs[$specificPreferenceKey]
                ?? $prefs['order_updates']
                ?? true
            );
        }

        return (bool) ($prefs['order_updates'] ?? true);
    }

    protected function shouldSendDeliveryPush(object $user): bool
    {
        $prefs = $this->notificationPreferences($user);

        if (($prefs['order_notifications'] ?? true) === false) {
            return false;
        }

        return (bool) ($prefs['delivery_updates'] ?? $prefs['order_updates'] ?? true);
    }

    protected function shouldSendReviewPush(object $user): bool
    {
        $prefs = $this->notificationPreferences($user);

        return (bool) ($prefs['review_notifications'] ?? true);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function expoPushPayload(
        string $title,
        string $body,
        string $channelId,
        array $data,
    ): array {
        return [
            'title' => $title,
            'body' => $body,
            'channelId' => $channelId,
            'sound' => 'default',
            'priority' => 'high',
            'data' => $data,
        ];
    }
}
