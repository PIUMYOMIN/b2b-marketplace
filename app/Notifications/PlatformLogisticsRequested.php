<?php

namespace App\Notifications;

use App\Models\Delivery;
use App\Notifications\Concerns\SendsExpoPush;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PlatformLogisticsRequested extends Notification
{
    use SendsExpoPush;

    public function __construct(public Delivery $delivery) {}

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
        $delivery = $this->delivery->loadMissing('order.seller.sellerProfile', 'supplier');
        $order = $delivery->order;
        $sellerName = $order?->seller?->sellerProfile?->store_name
            ?? $order?->seller?->name
            ?? $delivery->supplier?->name
            ?? 'Seller';

        return (new MailMessage)
            ->subject("Platform logistics requested - Order #{$order?->order_number}")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$sellerName} selected platform logistics for order #{$order?->order_number}.")
            ->line('Please review pickup details, assign a courier if needed, and manage the delivery from the platform logistics dashboard.')
            ->line('Pickup address: ' . ($delivery->pickup_address ?: 'Not provided'))
            ->line('Delivery fee: ' . number_format((float) $delivery->platform_delivery_fee) . ' MMK')
            ->action('Open Platform Logistics', $this->dashboardUrl())
            ->line('This delivery is awaiting platform pickup.');
    }

    public function toArray($notifiable): array
    {
        $delivery = $this->delivery->loadMissing('order.seller.sellerProfile', 'supplier');
        $order = $delivery->order;
        $sellerName = $order?->seller?->sellerProfile?->store_name
            ?? $order?->seller?->name
            ?? $delivery->supplier?->name
            ?? 'Seller';

        return [
            'type' => 'platform_logistics_requested',
            'delivery_id' => $delivery->id,
            'order_id' => $order?->id,
            'order_number' => $order?->order_number,
            'seller_id' => $order?->seller_id ?? $delivery->supplier_id,
            'seller_name' => $sellerName,
            'delivery_status' => $delivery->status,
            'delivery_method' => $delivery->delivery_method,
            'platform_delivery_fee' => (float) $delivery->platform_delivery_fee,
            'pickup_address' => $delivery->pickup_address,
            'url' => '/admin/dashboard?tab=platform-logistics',
            'message' => "{$sellerName} requested platform logistics for order #{$order?->order_number}.",
        ];
    }

    public function toExpoPush($notifiable): array
    {
        $delivery = $this->delivery->loadMissing('order.seller.sellerProfile', 'supplier');
        $order = $delivery->order;
        $sellerName = $order?->seller?->sellerProfile?->store_name
            ?? $order?->seller?->name
            ?? $delivery->supplier?->name
            ?? 'Seller';
        $body = "{$sellerName} requested platform logistics for #{$order?->order_number}.";

        return $this->expoPushPayload(
            'Platform Logistics',
            $body,
            'delivery',
            [
                'type' => 'platform_logistics_requested',
                'delivery_id' => (string) $delivery->id,
                'order_id' => (string) ($order?->id ?? ''),
                'order_number' => (string) ($order?->order_number ?? ''),
                'message' => $body,
                'url' => '/admin/dashboard?tab=platform-logistics',
            ],
        );
    }

    private function dashboardUrl(): string
    {
        $frontend = rtrim((string) config('app.frontend_url', config('app.url')), '/');

        return $frontend . '/admin/dashboard?tab=platform-logistics';
    }
}