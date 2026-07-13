<?php

namespace App\Notifications;

use App\Models\Order;
use App\Notifications\Channels\ExpoPushChannel;
use App\Notifications\Concerns\SendsExpoPush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderStatusChanged extends Notification
{
    use Queueable;
    use SendsExpoPush;

    public function __construct(public Order $order, public string $previousStatus) {}

    public function via($notifiable): array
    {
        $channels = ['database'];

        if (!empty($notifiable->email) && $this->shouldSend($notifiable)) {
            $channels[] = 'mail';
        }

        if ($this->shouldSend($notifiable)) {
            $channels[] = ExpoPushChannel::class;
        }

        return $channels;
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject("Order Update — #{$this->order->order_number}")
            ->view('emails.order-status-changed', [
                'order' => $this->order->load('items', 'buyer', 'delivery'),
            ]);
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'order_status_changed',
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'status' => $this->order->status,
            'message' => "Order #{$this->order->order_number} is now " . ucfirst($this->order->status) . '.',
        ];
    }

    public function toExpoPush($notifiable): array
    {
        $statusLabel = ucfirst(str_replace('_', ' ', (string) $this->order->status));
        $body = "Order #{$this->order->order_number} is now {$statusLabel}.";

        return $this->expoPushPayload(
            'Order Update',
            $body,
            'orders',
            [
                'type' => 'order_status_changed',
                'order_id' => (string) $this->order->id,
                'order_number' => $this->order->order_number,
                'status' => $this->order->status,
                'message' => $body,
            ],
        );
    }

    public function shouldSend($user): bool
    {
        return $this->shouldSendOrderPush($user);
    }
}
