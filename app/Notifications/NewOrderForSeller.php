<?php

namespace App\Notifications;

use App\Models\Order;
use App\Notifications\Concerns\SendsExpoPush;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewOrderForSeller extends Notification
{
    use SendsExpoPush;

    public function __construct(public Order $order) {}

    public function via($notifiable): array
    {
        $channels = ['database'];

        if (!empty($notifiable->email) && $this->shouldSendMail($notifiable)) {
            $channels[] = 'mail';
        }

        $channels = array_merge($channels, $this->mobilePushChannels($this->shouldSendNewOrderPush($notifiable)));

        return $channels;
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("New Order Received — #{$this->order->order_number}")
            ->view('emails.new-order-seller', [
                'order' => $this->order->load('items', 'buyer'),
                'notifiable' => $notifiable,
            ]);
    }

    public function toArray($notifiable): array
    {
        return [
            'type'         => 'new_order',
            'order_id'     => $this->order->id,
            'order_number' => $this->order->order_number,
            'message'      => "You have received a new order #{$this->order->order_number}.",
        ];
    }

    public function toExpoPush($notifiable): array
    {
        $body = "You have received a new order #{$this->order->order_number}.";

        return $this->expoPushPayload(
            'New Order Received',
            $body,
            'orders',
            [
                'type' => 'new_order',
                'order_id' => (string) $this->order->id,
                'order_number' => $this->order->order_number,
                'message' => $body,
            ],
        );
    }

    private function shouldSendMail($user): bool
    {
        $prefs = $this->notificationPreferences($user);

        return (bool) (
            $prefs['new_order']
            ?? $prefs['new_orders']
            ?? $prefs['order_updates']
            ?? true
        );
    }

    private function shouldSendNewOrderPush($user): bool
    {
        if (!$this->pushNotificationsEnabled($user)) {
            return false;
        }

        $prefs = $this->notificationPreferences($user);

        if (($prefs['order_notifications'] ?? true) === false) {
            return false;
        }

        return (bool) (
            $prefs['new_order']
            ?? $prefs['new_orders']
            ?? $prefs['order_updates']
            ?? true
        );
    }
}
