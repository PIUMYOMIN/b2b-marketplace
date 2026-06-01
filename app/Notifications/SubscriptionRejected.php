<?php

namespace App\Notifications;

use App\Models\SellerSubscription;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionRejected extends Notification
{
    public function __construct(public SellerSubscription $subscription, public string $reason) {}

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
            ->subject("Your {$plan?->name} subscription request was not approved")
            ->greeting('Subscription request update')
            ->line("We reviewed your {$plan?->name} subscription payment, but we could not approve it at this time.")
            ->line('Payment reference number: ' . ($this->subscription->payment_reference ?: 'Not provided'))
            ->line('Reason: ' . $this->reason)
            ->line('Please check your payment details and submit the request again when ready.')
            ->action('Review Subscription', $dashboard)
            ->line('If you believe this was a mistake, please contact Pyonea support.');
    }

    public function toArray($notifiable): array
    {
        $plan = $this->subscription->plan;

        return [
            'type' => 'subscription_rejected',
            'subscription_id' => $this->subscription->id,
            'plan_name' => $plan?->name,
            'payment_reference' => $this->subscription->payment_reference,
            'reason' => $this->reason,
            'url' => '/seller/dashboard?tab=subscription',
            'message' => "Your {$plan?->name} subscription request was rejected. Reason: {$this->reason}",
        ];
    }
}
