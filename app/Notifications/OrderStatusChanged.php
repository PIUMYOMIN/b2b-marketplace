<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderStatusChanged extends Notification
{
    use Queueable;

    public function __construct(public Order $order, public string $previousStatus) {}

    public function via($notifiable): array
    {
        return $this->shouldSend($notifiable) ? ['mail', 'database'] : ['database'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject("Order Update — #{$this->order->order_number}")
            ->view('emails.order-status-changed', [
                'order' => $this->order->load('items', 'buyer', 'delivery')
            ]);
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'order_status_changed',
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'status' => $this->order->status,
            'message' => "Order #{$this->order->order_number} is now " . ucfirst($this->order->status) . "."
        ];
    }

    // Make public for NotificationSender access
    public function shouldSend($user): bool
    {
        $prefs = $user->notification_preferences;

        // Ensure $prefs is an array
        if (is_string($prefs)) {
            $prefs = json_decode($prefs, true) ?: [];
        } elseif (!is_array($prefs)) {
            $prefs = [];
        }

        // Default to true if the key is missing
        return $prefs['order_updates'] ?? true;
    }
}
