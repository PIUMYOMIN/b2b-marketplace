<?php

namespace App\Notifications\Concerns;

use App\Notifications\Channels\ExpoPushChannel;

trait SendsExpoPush
{
    /** @return array<int, class-string> */
    protected function mobilePushChannels(bool $shouldSend): array
    {
        if (!$shouldSend) {
            return [];
        }

        return [ExpoPushChannel::class];
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

    protected function pushNotificationsEnabled(object $user): bool
    {
        $prefs = $this->notificationPreferences($user);

        return (bool) ($prefs['push_notifications'] ?? true);
    }

    /**
     * Transactional emails (order confirmations, status updates, OTP, etc.)
     * must not be suppressed by push notification preferences.
     */
    protected function shouldSendTransactionalMail(object $user): bool
    {
        return true;
    }

    protected function shouldSendOrderPush(object $user, ?string $specificPreferenceKey = null): bool
    {
        if (!$this->pushNotificationsEnabled($user)) {
            return false;
        }

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
        if (!$this->pushNotificationsEnabled($user)) {
            return false;
        }

        $prefs = $this->notificationPreferences($user);

        if (($prefs['order_notifications'] ?? true) === false) {
            return false;
        }

        return (bool) ($prefs['delivery_updates'] ?? $prefs['order_updates'] ?? true);
    }

    protected function shouldSendReviewPush(object $user): bool
    {
        if (!$this->pushNotificationsEnabled($user)) {
            return false;
        }

        $prefs = $this->notificationPreferences($user);

        return (bool) ($prefs['review_notifications'] ?? true);
    }

    protected function shouldSendMessagePush(object $user): bool
    {
        if (!$this->pushNotificationsEnabled($user)) {
            return false;
        }

        $prefs = $this->notificationPreferences($user);

        return (bool) ($prefs['message_notifications'] ?? true);
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

        if ($type === 'rfq_quote_received') {
            return "{$base}/rfq";
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

        if ($type === 'subscription_request') {
            return "{$base}/admin/dashboard?tab=subscriptions";
        }

        if (str_starts_with($type, 'subscription_') || $type === 'product_limit_warning') {
            return "{$base}/seller/dashboard?tab=subscription";
        }

        if (in_array($type, ['cod_invoice_warning', 'cod_invoice_suspension'], true)) {
            return "{$base}/seller/dashboard?tab=wallet";
        }

        if ($type === 'platform_logistics_requested') {
            return "{$base}/admin/dashboard?tab=platform-logistics";
        }

        if ($type === 'self_delivery_completed') {
            return "{$base}/admin/dashboard?tab=orders";
        }

        if ($type === 'new_user_registered') {
            return "{$base}/admin/dashboard?tab=users";
        }

        if (in_array($type, ['new_report', 'report_reporter_replied'], true) && !empty($data['ticket_id'])) {
            return "{$base}/admin/dashboard?tab=reports&ticket=" . rawurlencode((string) $data['ticket_id']);
        }

        return null;
    }
}
