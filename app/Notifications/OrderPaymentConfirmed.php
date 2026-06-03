<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderPaymentConfirmed extends Notification
{
    public function __construct(public Order $order) {}

    public function via($notifiable): array
    {
        $channels = ['database'];

        if (! empty($notifiable->email) && $this->shouldSendMail($notifiable)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail($notifiable): MailMessage
    {
        $order = $this->order->loadMissing('buyer', 'items');

        return (new MailMessage)
            ->subject("Payment Confirmed — Order #{$order->order_number}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Payment has been confirmed for order #{$order->order_number}.")
            ->line('The order is now confirmed and ready for processing.')
            ->line('Payment method: ' . str_replace('_', ' ', strtoupper($order->payment_method)))
            ->line('Amount: ' . number_format((float) $order->total_amount) . ' MMK')
            ->action('View Orders', url('/seller/orders'))
            ->line('Please prepare the order and keep your buyer updated.');
    }

    public function toArray($notifiable): array
    {
        return [
            'type'           => 'order_payment_confirmed',
            'order_id'       => $this->order->id,
            'order_number'   => $this->order->order_number,
            'payment_method' => $this->order->payment_method,
            'amount'         => (float) $this->order->total_amount,
            'message'        => "Payment confirmed for order #{$this->order->order_number}.",
        ];
    }

    private function shouldSendMail($user): bool
    {
        $prefs = $user->notification_preferences;

        if (is_string($prefs)) {
            $prefs = json_decode($prefs, true) ?: [];
        } elseif (! is_array($prefs)) {
            $prefs = [];
        }

        return ($prefs['payment_confirmed'] ?? $prefs['order_updates'] ?? true);
    }
}
