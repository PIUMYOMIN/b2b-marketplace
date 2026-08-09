<?php

namespace App\Notifications;

use App\Models\SellerProfile;
use App\Notifications\Concerns\SendsExpoPush;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SellerRejected extends Notification
{
    use SendsExpoPush;

    public function __construct(public SellerProfile $profile, public ?string $reason = null) {}

    public function via($notifiable): array
    {
        $channels = ['database'];

        if (!empty($notifiable->email)) {
            $channels[] = 'mail';
        }

        return array_merge($channels, $this->mobilePushChannels($this->pushNotificationsEnabled($notifiable)));
    }

    public function toMail($n)
    {
        return (new MailMessage)
            ->subject('Pyonea Seller Application Update')
            ->view('emails.seller-rejected', ['seller' => $this->profile, 'reason' => $this->reason]);
    }

    public function toArray($n): array
    {
        return [
            'type' => 'seller_rejected',
            'reason' => $this->reason,
            'message' => 'Your seller application requires attention. Please check your email for details.',
            'url' => '/seller/dashboard?tab=settings',
        ];
    }

    public function toExpoPush($notifiable): array
    {
        $body = 'Your seller application needs attention. Open the app for details.';

        return $this->expoPushPayload(
            'Seller Application Update',
            $body,
            'admin',
            [
                'type' => 'seller_rejected',
                'reason' => (string) ($this->reason ?? ''),
                'message' => $body,
                'url' => '/seller/dashboard?tab=settings',
            ],
        );
    }
}
