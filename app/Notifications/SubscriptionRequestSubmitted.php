<?php

namespace App\Notifications;

use App\Models\SellerSubscription;
use Illuminate\Notifications\Notification;

class SubscriptionRequestSubmitted extends Notification
{
    public function __construct(public SellerSubscription $subscription) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        $seller = $this->subscription->user;
        $plan = $this->subscription->plan;

        return [
            'type' => 'subscription_request',
            'subscription_id' => $this->subscription->id,
            'seller_id' => $this->subscription->user_id,
            'seller_name' => $seller?->sellerProfile?->store_name ?? $seller?->name ?? 'Seller',
            'plan_name' => $plan?->name,
            'amount_mmk' => $this->subscription->amount_paid_mmk,
            'payment_reference' => $this->subscription->payment_reference,
            'message' => ($seller?->sellerProfile?->store_name ?? $seller?->name ?? 'A seller')
                . " requested {$plan?->name} plan approval.",
        ];
    }
}
