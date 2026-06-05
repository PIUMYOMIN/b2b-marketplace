<?php

namespace App\Notifications;

use App\Models\Delivery;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SelfDeliveryCompleted extends Notification
{
    public function __construct(public Delivery $delivery) {}

    public function via($notifiable): array
    {
        $channels = ['database'];

        if (! empty($notifiable->email) && $this->shouldSendMail($notifiable)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail($notifiable): MailMessage
    {
        $delivery = $this->delivery->loadMissing('order.buyer', 'order.seller.sellerProfile', 'supplier');
        $order = $delivery->order;
        $sellerName = $order?->seller?->sellerProfile?->store_name
            ?? $order?->seller?->name
            ?? $delivery->supplier?->name
            ?? 'Seller';
        $buyerName = $order?->buyer?->name ?? 'Buyer';

        return (new MailMessage)
            ->subject("Self delivery completed - Order #{$order?->order_number}")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$sellerName} marked self delivery completed for order #{$order?->order_number}.")
            ->line("Buyer: {$buyerName}")
            ->line('Please review the order, payment, proof image, escrow, and payout details from the admin dashboard.')
            ->action('Review Order', $this->dashboardUrl())
            ->line('This notification is for admin verification before seller payout handling.');
    }

    public function toArray($notifiable): array
    {
        $delivery = $this->delivery->loadMissing('order.buyer', 'order.seller.sellerProfile', 'supplier');
        $order = $delivery->order;
        $sellerName = $order?->seller?->sellerProfile?->store_name
            ?? $order?->seller?->name
            ?? $delivery->supplier?->name
            ?? 'Seller';

        return [
            'type' => 'self_delivery_completed',
            'delivery_id' => $delivery->id,
            'order_id' => $order?->id,
            'order_number' => $order?->order_number,
            'seller_id' => $order?->seller_id ?? $delivery->supplier_id,
            'seller_name' => $sellerName,
            'buyer_id' => $order?->buyer_id,
            'delivery_status' => $delivery->status,
            'delivery_method' => $delivery->delivery_method,
            'delivered_at' => $delivery->delivered_at?->toIso8601String(),
            'proof_image' => $delivery->delivery_proof_image,
            'payment_method' => $order?->payment_method,
            'payment_status' => $order?->payment_status,
            'escrow_status' => $order?->escrow_status,
            'seller_payout' => $order ? (float) ((float) $order->subtotal_amount - (float) $order->commission_amount) : null,
            'commission_amount' => $order ? (float) $order->commission_amount : null,
            'url' => '/admin/dashboard?tab=orders',
            'message' => "{$sellerName} completed self delivery for order #{$order?->order_number}. Please review before payout.",
        ];
    }

    private function dashboardUrl(): string
    {
        $frontend = rtrim((string) config('app.frontend_url', config('app.url')), '/');

        return $frontend . '/admin/dashboard?tab=orders';
    }

    private function shouldSendMail($user): bool
    {
        $prefs = $user->notification_preferences;

        if (is_string($prefs)) {
            $prefs = json_decode($prefs, true) ?: [];
        } elseif (! is_array($prefs)) {
            $prefs = [];
        }

        return $prefs['delivery_updates'] ?? $prefs['order_updates'] ?? true;
    }
}
