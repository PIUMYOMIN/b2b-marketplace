<?php

namespace App\Notifications;

use App\Models\Order;
use App\Notifications\Concerns\SendsExpoPush;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderPlaced extends Notification
{
    use SendsExpoPush;

    public function __construct(public Order $order) {}

    public function via($notifiable): array
    {
        $channels = ['database'];

        if (!empty($notifiable->email) && $this->shouldSendMail($notifiable)) {
            $channels[] = 'mail';
        }

        $channels = array_merge($channels, $this->mobilePushChannels($this->shouldSendOrderPush($notifiable)));

        return $channels;
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Order Confirmed — #{$this->order->order_number}")
            ->view('emails.order-placed', [
                'order' => $this->order->load('items', 'buyer', 'delivery'),
            ]);
    }

    public function toArray($notifiable): array
    {
        return [
            'type'         => 'order_placed',
            'order_id'     => $this->order->id,
            'order_number' => $this->order->order_number,
            'message'      => "Your order #{$this->order->order_number} has been placed successfully.",
        ];
    }

    public function toExpoPush($notifiable): array
    {
        $body = "Your order #{$this->order->order_number} has been placed successfully.";

        return $this->expoPushPayload(
            'Order Confirmed',
            $body,
            'orders',
            [
                'type' => 'order_placed',
                'order_id' => (string) $this->order->id,
                'order_number' => $this->order->order_number,
                'message' => $body,
            ],
        );
    }

    private function shouldSendMail($user): bool
    {
        return $this->shouldSendOrderPush($user);
    }
}
