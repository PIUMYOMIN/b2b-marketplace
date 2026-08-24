<?php

namespace App\Notifications;

use App\Models\PayoutRequest;
use App\Notifications\Concerns\SendsExpoPush;
use Illuminate\Notifications\Notification;

class PayoutRequestSubmitted extends Notification
{
    use SendsExpoPush;

    public function __construct(public PayoutRequest $payoutRequest) {}

    public function via($notifiable): array
    {
        return array_merge(['database'], $this->mobilePushChannels($this->pushNotificationsEnabled($notifiable)));
    }

    public function toArray($notifiable): array
    {
        $request = $this->payoutRequest->loadMissing('seller');
        $sellerName = $request->seller?->name ?? 'Seller';
        $message = "{$sellerName} requested a seller payout of " . number_format((float) $request->amount) . ' MMK.';

        return [
            'type' => 'seller_payout_requested',
            'payout_request_id' => $request->id,
            'seller_id' => $request->seller_id,
            'seller_name' => $sellerName,
            'amount' => (float) $request->amount,
            'payment_method' => $request->payment_method,
            'message' => $message,
            'url' => '/admin/dashboard?tab=payouts',
        ];
    }

    public function toExpoPush($notifiable): array
    {
        $data = $this->toArray($notifiable);
        return $this->expoPushPayload('Seller payout requested', $data['message'], 'admin', $data);
    }
}
