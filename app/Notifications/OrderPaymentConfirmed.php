<?php

namespace App\Notifications;

use App\Models\Order;
use App\Notifications\Concerns\SendsExpoPush;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderPaymentConfirmed extends Notification
{
    use SendsExpoPush;

    public function __construct(public Order $order) {}

    public function via($notifiable): array
    {
        $channels = ['database'];

        if (!empty($notifiable->email) && $this->shouldSendMail($notifiable)) {
            $channels[] = 'mail';
        }

        $channels = array_merge($channels, $this->mobilePushChannels($this->shouldSendPush($notifiable)));

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
            ->action('View Orders', rtrim((string) config('app.frontend_url', 'https://pyonea.com'), '/') . '/seller/dashboard?tab=orders')
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

    public function toExpoPush($notifiable): array
    {
        $body = "Payment confirmed for order #{$this->order->order_number}.";

        return $this->expoPushPayload(
            'Payment Confirmed',
            $body,
            'orders',
            [
                'type' => 'order_payment_confirmed',
                'order_id' => (string) $this->order->id,
                'order_number' => $this->order->order_number,
                'message' => $body,
            ],
        );
    }

    private function shouldSendMail($user): bool
    {
        $prefs = $this->notificationPreferences($user);

        return (bool) ($prefs['payment_confirmed'] ?? $prefs['order_updates'] ?? true);
    }

    private function shouldSendPush($user): bool
    {
        $prefs = $this->notificationPreferences($user);

        if (($prefs['order_notifications'] ?? true) === false) {
            return false;
        }

        return (bool) ($prefs['payment_confirmed'] ?? $prefs['order_updates'] ?? true);
    }
}
