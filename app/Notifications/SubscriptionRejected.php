<?php

namespace App\Notifications;

use App\Models\SellerSubscription;
use Illuminate\Notifications\Notification;

class SubscriptionRejected extends Notification
{
    public function __construct(public SellerSubscription $subscription, public string $reason) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        $plan = $this->subscription->plan;

        return [
            'type' => 'subscription_rejected',
            'subscription_id' => $this->subscription->id,
            'plan_name' => $plan?->name,
            'reason' => $this->reason,
            'message' => "Your {$plan?->name} subscription request was rejected. Reason: {$this->reason}",
        ];
    }
}
