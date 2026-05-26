<?php

namespace App\Notifications;

use App\Models\SellerSubscription;
use Illuminate\Notifications\Notification;

class SubscriptionApproved extends Notification
{
    public function __construct(public SellerSubscription $subscription) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        $plan = $this->subscription->plan;

        return [
            'type' => 'subscription_approved',
            'subscription_id' => $this->subscription->id,
            'plan_name' => $plan?->name,
            'ends_at' => $this->subscription->ends_at?->toDateString(),
            'message' => "Your {$plan?->name} subscription is approved and active.",
        ];
    }
}
