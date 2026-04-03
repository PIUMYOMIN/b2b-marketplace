<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderPlaced extends Notification
{
    // No Queueable — send synchronously so buyer gets confirmation immediately

    public function __construct(public Order $order) {}

    public function via($notifiable): array
    {
        $channels = ['database'];
        if (!empty($notifiable->email) && $this->shouldSendMail($notifiable)) {
            $channels[] = 'mail';
        }
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

    private function shouldSendMail($user): bool
    {
        $prefs = $user->notification_preferences;
        if (is_string($prefs)) {
            $prefs = json_decode($prefs, true) ?: [];
        } elseif (!is_array($prefs)) {
            $prefs = [];
        }
        return $prefs['order_updates'] ?? true;
    }
}