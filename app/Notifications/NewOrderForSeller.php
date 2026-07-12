<?php

namespace App\Notifications;

use App\Models\Order;
use App\Notifications\Channels\ExpoPushChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewOrderForSeller extends Notification
{
    // No Queueable — send synchronously so seller gets the email immediately

    public function __construct(public Order $order) {}

    public function via($notifiable): array
    {
        $channels = ['database'];

        if (!empty($notifiable->email) && $this->shouldSendMail($notifiable)) {
            $channels[] = 'mail';
        }

        if ($this->shouldSendPush($notifiable)) {
            $channels[] = ExpoPushChannel::class;
        }

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
        return [
            'title' => 'New Order Received',
            'body' => "You have received a new order #{$this->order->order_number}.",
            'channelId' => 'orders',
            'sound' => 'default',
            'priority' => 'high',
            'data' => [
                'type' => 'new_order',
                'order_id' => (string) $this->order->id,
                'order_number' => $this->order->order_number,
                'message' => "You have received a new order #{$this->order->order_number}.",
            ],
        ];
    }

    private function notificationPreferences($user): array
    {
        $prefs = $user->notification_preferences;
        if (is_string($prefs)) {
            $prefs = json_decode($prefs, true) ?: [];
        } elseif (!is_array($prefs)) {
            $prefs = [];
        }

        return $prefs;
    }

    private function shouldSendMail($user): bool
    {
        $prefs = $this->notificationPreferences($user);

        return ($prefs['new_order'] ?? $prefs['order_updates'] ?? true);
    }

    private function shouldSendPush($user): bool
    {
        $prefs = $this->notificationPreferences($user);
        $orderNotificationsEnabled = $prefs['order_notifications'] ?? true;
        $newOrderEnabled = $prefs['new_order']
            ?? $prefs['new_orders']
            ?? $prefs['order_updates']
            ?? true;

        return $orderNotificationsEnabled && $newOrderEnabled;
    }
}
