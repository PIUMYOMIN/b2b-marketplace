<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewOrderForSeller extends Notification
{
    // No Queueable — send synchronously so seller gets the email immediately

    public function __construct(public Order $order) {}

    public function via($notifiable): array
    {
        // Only send mail if the seller has an email address
        $channels = ['database'];
        if (!empty($notifiable->email) && $this->shouldSendMail($notifiable)) {
            $channels[] = 'mail';
        }
        return $channels;
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("New Order Received — #{$this->order->order_number}")
            ->view('emails.new-order-seller', [
                'order' => $this->order->load('items', 'buyer'),
                // $notifiable is passed automatically by Laravel as the mail recipient
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

    private function shouldSendMail($user): bool
    {
        $prefs = $user->notification_preferences;
        if (is_string($prefs)) {
            $prefs = json_decode($prefs, true) ?: [];
        } elseif (!is_array($prefs)) {
            $prefs = [];
        }
        // Check both possible keys for backward compatibility
        return ($prefs['new_order'] ?? $prefs['order_updates'] ?? true);
    }
}