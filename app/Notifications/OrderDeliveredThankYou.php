<?php

namespace App\Notifications;

use App\Models\Order;
use App\Notifications\Concerns\SendsExpoPush;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the buyer after they confirm delivery (order status → delivered).
 * Fires synchronously — no queue — so it arrives immediately.
 */
class OrderDeliveredThankYou extends Notification
{
    use SendsExpoPush;

    public function __construct(public Order $order) {}

    public function via($notifiable): array
    {
        $channels = ['database'];

        if (!empty($notifiable->email) && $this->shouldSendTransactionalMail($notifiable)) {
            $channels[] = 'mail';
        }

        $channels = array_merge($channels, $this->mobilePushChannels($this->shouldSendOrderPush($notifiable)));

        return $channels;
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Thank you for your order - #{$this->order->order_number}")
            ->view('emails.order-delivered-thank-you', [
                'order' => $this->order->load('items', 'buyer', 'seller.sellerProfile'),
            ]);
    }

    public function toArray($notifiable): array
    {
        return [
            'type'         => 'order_delivered_thank_you',
            'order_id'     => $this->order->id,
            'order_number' => $this->order->order_number,
            'message'      => "Thank you! Your order #{$this->order->order_number} has been delivered. We hope you love your purchase.",
        ];
    }

    public function toExpoPush($notifiable): array
    {
        $body = "Thank you! Your order #{$this->order->order_number} has been delivered.";

        return $this->expoPushPayload(
            'Order Delivered',
            $body,
            'orders',
            [
                'type' => 'order_delivered_thank_you',
                'order_id' => (string) $this->order->id,
                'order_number' => $this->order->order_number,
                'message' => $body,
            ],
        );
    }
}
