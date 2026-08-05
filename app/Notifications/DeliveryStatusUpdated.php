<?php

namespace App\Notifications;

use App\Models\Delivery;
use App\Notifications\Concerns\SendsExpoPush;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DeliveryStatusUpdated extends Notification
{
    use SendsExpoPush;

    public function __construct(
        public Delivery $delivery,
        public ?string $previousStatus = null,
        public bool $sendMail = true,
    ) {}

    public function via($notifiable): array
    {
        $channels = ['database'];

        if ($this->sendMail && !empty($notifiable->email) && $this->shouldSendTransactionalMail($notifiable)) {
            $channels[] = 'mail';
        }

        $channels = array_merge($channels, $this->mobilePushChannels($this->shouldSendDeliveryPush($notifiable)));

        return $channels;
    }

    public function toMail($notifiable): MailMessage
    {
        $delivery = $this->delivery->loadMissing('order.buyer', 'order.seller.sellerProfile');
        $order = $delivery->order;

        return (new MailMessage)
            ->subject("Delivery Update - #{$order?->order_number}")
            ->view('emails.delivery-status-updated', [
                'delivery' => $delivery,
                'order' => $order,
                'previousStatus' => $this->previousStatus,
                'notifiable' => $notifiable,
            ]);
    }

    public function toArray($notifiable): array
    {
        $delivery = $this->delivery->loadMissing('order');
        $order = $delivery->order;
        $statusLabel = $this->label($delivery->status);

        return [
            'type' => 'delivery_status_changed',
            'delivery_id' => $delivery->id,
            'order_id' => $order?->id,
            'order_number' => $order?->order_number,
            'delivery_status' => $delivery->status,
            'previous_status' => $this->previousStatus,
            'tracking_number' => $delivery->tracking_number,
            'message' => "Delivery for order #{$order?->order_number} is now {$statusLabel}.",
        ];
    }

    public function toExpoPush($notifiable): array
    {
        $delivery = $this->delivery->loadMissing('order');
        $order = $delivery->order;
        $orderNumber = (string) ($order?->order_number ?? '');
        $statusLabel = $this->label($delivery->status);
        $body = $orderNumber !== ''
            ? "Delivery for order #{$orderNumber} is now {$statusLabel}."
            : "Your delivery is now {$statusLabel}.";

        return $this->expoPushPayload(
            'Delivery Update',
            $body,
            'delivery',
            [
                'type' => 'delivery_status_changed',
                'delivery_id' => (string) $delivery->id,
                'order_id' => $order?->id ? (string) $order->id : '',
                'order_number' => $orderNumber,
                'delivery_status' => $delivery->status,
                'tracking_number' => $delivery->tracking_number,
                'message' => $body,
            ],
        );
    }

    private function label(?string $status): string
    {
        return match ($status) {
            'awaiting_pickup' => 'awaiting pickup',
            'picked_up' => 'picked up',
            'in_transit' => 'in transit',
            'out_for_delivery' => 'out for delivery',
            'delivered' => 'delivered',
            'failed' => 'failed',
            'cancelled' => 'cancelled',
            'returned' => 'returned',
            default => str_replace('_', ' ', (string) $status),
        };
    }
}
