<?php
namespace App\Notifications;
use App\Models\SellerProfile;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SellerApproved extends Notification {
    public function __construct(public SellerProfile $profile) {}
    public function via($notifiable): array
    {
        $channels = ['database'];
        if (!empty($notifiable->email)) {
            $channels[] = 'mail';
        }
        return $channels;
    }
    public function toMail($n) {
        return (new MailMessage)
            ->subject('🎉 Your Pyonea Seller Account is Approved!')
            ->view('emails.seller-approved', ['seller'=>$this->profile]);
    }
    public function toArray($n): array {
        return ['type'=>'seller_approved','message'=>'Your seller account has been approved. You can now start selling!'];
    }
}