<?php

namespace App\Notifications;

use App\Models\SellerSubscription;
use App\Notifications\Concerns\SendsExpoPush;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionRequestSubmitted extends Notification
{
    use SendsExpoPush;

    public function __construct(public SellerSubscription $subscription) {}

    public function via($notifiable): array
    {
        $channels = ['database'];

        if (! empty($notifiable->email)) {
            $channels[] = 'mail';
        }

        return array_merge($channels, $this->mobilePushChannels($this->pushNotificationsEnabled($notifiable)));
    }

    public function toMail($notifiable): MailMessage
    {
        $seller = $this->subscription->user;
        $plan = $this->subscription->plan;
        $sellerName = $seller?->sellerProfile?->store_name ?? $seller?->name ?? 'A seller';
        $dashboard = rtrim(config('app.frontend_url'), '/') . '/admin/dashboard?tab=subscriptions';

        return (new MailMessage)
            ->subject("New subscription request - {$plan?->name}")
            ->greeting('New Seller Subscription Request')
            ->line("{$sellerName} requested approval for the {$plan?->name} plan.")
            ->line('Seller email: ' . ($seller?->email ?? 'Not provided'))
            ->line('Amount: ' . number_format((float) $this->subscription->amount_paid_mmk) . ' MMK')
            ->line('Payment method: ' . ($this->paymentMethodLabel($this->subscription->payment_method) ?: 'Not provided'))
            ->line('Payment reference number: ' . ($this->subscription->payment_reference ?: 'Not provided'))
            ->action('Review Subscription Request', $dashboard)
            ->line('Please verify the payment and approve or reject the request from the admin dashboard.');
    }

    public function toArray($notifiable): array
    {
        $seller = $this->subscription->user;
        $plan = $this->subscription->plan;
        $dashboardPath = '/admin/dashboard?tab=subscriptions';

        return [
            'type' => 'subscription_request',
            'subscription_id' => $this->subscription->id,
            'seller_id' => $this->subscription->user_id,
            'seller_name' => $seller?->sellerProfile?->store_name ?? $seller?->name ?? 'Seller',
            'plan_name' => $plan?->name,
            'amount_mmk' => $this->subscription->amount_paid_mmk,
            'payment_reference' => $this->subscription->payment_reference,
            'payment_method' => $this->subscription->payment_method,
            'payment_method_label' => $this->paymentMethodLabel($this->subscription->payment_method),
            'url' => $dashboardPath,
            'message' => ($seller?->sellerProfile?->store_name ?? $seller?->name ?? 'A seller')
                . " requested {$plan?->name} plan approval.",
        ];
    }

    public function toExpoPush($notifiable): array
    {
        $seller = $this->subscription->user;
        $sellerName = $seller?->sellerProfile?->store_name ?? $seller?->name ?? 'A seller';
        $planName = $this->subscription->plan?->name ?? 'a plan';
        $body = "{$sellerName} requested {$planName} approval.";

        return $this->expoPushPayload(
            'Subscription Request',
            $body,
            'admin',
            [
                'type' => 'subscription_request',
                'subscription_id' => (string) $this->subscription->id,
                'seller_id' => (string) $this->subscription->user_id,
                'plan_name' => (string) $planName,
                'message' => $body,
                'url' => '/admin/dashboard?tab=subscriptions',
            ],
        );
    }

    private function paymentMethodLabel(?string $method): ?string
    {
        return match ($method) {
            'mmqr' => 'MMQR',
            'kbz_pay' => 'KBZ Pay',
            'wave_pay' => 'Wave Money',
            'cb_pay' => 'CB Pay',
            'aya_pay' => 'AYA Pay',
            'bank_transfer' => 'Bank Transfer',
            default => $method,
        };
    }
}