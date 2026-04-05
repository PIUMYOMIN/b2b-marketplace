<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the buyer after they confirm delivery (order status → delivered).
 * Fires synchronously — no queue — so it arrives immediately.
 */
class OrderDeliveredThankYou extends Notification
{
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
            ->subject("Thank you for your order — #{$this->order->order_number} 🙏")
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