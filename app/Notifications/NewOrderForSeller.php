<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewOrderForSeller extends Notification
{
    use Queueable;

    public function __construct(public Order $order) {}

    public function via($notifiable): array
    {
        return $this->shouldSend($notifiable) ? ['mail', 'database'] : ['database'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject("New Order Received — #{$this->order->order_number}")
            ->view('emails.new-order-seller', [
                'order' => $this->order->load('items', 'buyer', 'delivery')
            ]);
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'new_order',
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'message' => "You have received a new order #{$this->order->order_number}."
        ];
    }

    public function shouldSend($user): bool
    {
        $prefs = $user->notification_preferences;

        if (is_string($prefs)) {
            $prefs = json_decode($prefs, true) ?: [];
        } elseif (!is_array($prefs)) {
            $prefs = [];
        }

        // Default to true if no setting found
        return $prefs['new_order'] ?? true;
    }
}
