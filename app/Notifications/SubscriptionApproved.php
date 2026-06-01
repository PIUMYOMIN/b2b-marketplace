<?php

namespace App\Notifications;

use App\Models\SellerSubscription;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionApproved extends Notification
{
    public function __construct(public SellerSubscription $subscription) {}

    public function via($notifiable): array
    {
        $channels = ['database'];

        if (! empty($notifiable->email)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail($notifiable): MailMessage
    {
        $plan = $this->subscription->plan;
        $dashboard = rtrim(config('app.frontend_url'), '/') . '/seller/dashboard?tab=subscription';

        return (new MailMessage)
            ->subject("Your {$plan?->name} subscription is approved")
            ->greeting('Thank you for your payment')
            ->line("Good news! Your {$plan?->name} subscription has been approved and is now active.")
            ->line('Payment reference number: ' . ($this->subscription->payment_reference ?: 'Not provided'))
            ->line('Amount paid: ' . number_format((float) $this->subscription->amount_paid_mmk) . ' MMK')
            ->line('Active until: ' . ($this->subscription->ends_at?->toDateString() ?: 'No expiry date'))
            ->action('View Subscription', $dashboard)
            ->line('Thank you for growing your business with Pyonea.');
    }

    public function toArray($notifiable): array
    {
        $plan = $this->subscription->plan;

        return [
            'type' => 'subscription_approved',
            'subscription_id' => $this->subscription->id,
            'plan_name' => $plan?->name,
            'ends_at' => $this->subscription->ends_at?->toDateString(),
            'payment_reference' => $this->subscription->payment_reference,
            'url' => '/seller/dashboard?tab=subscription',
            'message' => "Your {$plan?->name} subscription is approved and active.",
        ];
    }
}
