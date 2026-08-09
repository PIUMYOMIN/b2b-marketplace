<?php

namespace App\Notifications;

use App\Models\SellerProfile;
use App\Notifications\Concerns\SendsExpoPush;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SellerApproved extends Notification
{
    use SendsExpoPush;

    public function __construct(public SellerProfile $profile) {}

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
            ->subject('Your Pyonea Seller Account is Approved!')
            ->view('emails.seller-approved', ['seller' => $this->profile]);
    }

    public function toArray($n): array
    {
        return [
            'type' => 'seller_approved',
            'message' => 'Your seller account has been approved. You can now start selling!',
            'url' => '/seller/dashboard?tab=settings',
        ];
    }

    public function toExpoPush($notifiable): array
    {
        $body = 'Your seller account has been approved. You can now start selling!';

        return $this->expoPushPayload(
            'Seller Approved',
            $body,
            'admin',
            [
                'type' => 'seller_approved',
                'message' => $body,
                'url' => '/seller/dashboard?tab=settings',
            ],
        );
    }
}
