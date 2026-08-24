<?php

namespace App\Notifications;

use App\Models\PayoutRequest;
use App\Notifications\Concerns\SendsExpoPush;
use Illuminate\Notifications\Notification;

class PayoutRequestUpdated extends Notification
{
    use SendsExpoPush;

    public function __construct(public PayoutRequest $payoutRequest) {}

    public function via($notifiable): array
    {
        return array_merge(['database'], $this->mobilePushChannels($this->pushNotificationsEnabled($notifiable)));
    }

    public function toArray($notifiable): array
    {
        $request = $this->payoutRequest;
        $status = str_replace('_', ' ', $request->status);
        $message = 'Your payout request #' . $request->id . " is now {$status}.";

        return [
            'type' => 'seller_payout_updated',
            'payout_request_id' => $request->id,
            'amount' => (float) $request->amount,
            'status' => $request->status,
            'message' => $message,
            'url' => '/seller/dashboard?tab=wallet',
        ];
    }

    public function toExpoPush($notifiable): array
    {
        $data = $this->toArray($notifiable);
        return $this->expoPushPayload('Payout request updated', $data['message'], 'seller', $data);
    }
}
