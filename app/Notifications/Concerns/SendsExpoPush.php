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
        if (!isset($data['url'])) {
            $derivedUrl = $this->derivePushDeepLink($data);
            if ($derivedUrl !== null) {
                $data['url'] = $derivedUrl;
            }
        }

        return [
            'title' => $title,
            'body' => $body,
            'channelId' => $channelId,
            'sound' => 'default',
            'priority' => 'high',
            'data' => $data,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function derivePushDeepLink(array $data): ?string
    {
        $base = rtrim((string) config('app.frontend_url', 'https://pyonea.com'), '/');
        $type = (string) ($data['type'] ?? '');

        if ($type === 'message_received' && !empty($data['conversation_id'])) {
            return "{$base}/messages/{$data['conversation_id']}";
        }

        if (!empty($data['order_number'])) {
            if ($type === 'new_order') {
                return "{$base}/seller/dashboard?tab=orders";
            }

            if (in_array($type, [
                'order_placed',
                'order_status_changed',
                'order_payment_confirmed',
                'order_delivered_thank_you',
            ], true)) {
                return "{$base}/buyer/dashboard?tab=orders";
            }

            if (
                str_contains($type, 'delivery')
                || $type === 'self_delivery_completed'
                || $type === 'delivery_status_changed'
            ) {
                return "{$base}/track-order?order=" . rawurlencode((string) $data['order_number']);
            }
        }

        if (str_starts_with($type, 'rfq_')) {
            return "{$base}/seller/dashboard?tab=rfq";
        }

        if ($type === 'product_review') {
            return "{$base}/seller/dashboard?tab=reviews";
        }

        if (in_array($type, ['seller_approved', 'seller_rejected', 'seller_review'], true)) {
            return "{$base}/seller/dashboard?tab=settings";
        }

        if (str_starts_with($type, 'subscription_') || $type === 'subscription_request') {
            return "{$base}/seller/dashboard?tab=subscription";
        }

        if ($type === 'new_user_registered') {
            return "{$base}/admin/dashboard?tab=overview";
        }

        return null;
    }
}
