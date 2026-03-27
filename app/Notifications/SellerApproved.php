<?php
namespace App\Notifications;
use App\Models\SellerProfile;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SellerApproved extends Notification {
    use Queueable;
    public function __construct(public SellerProfile $profile) {}
    public function via($n): array { return ['mail','database']; }
    public function toMail($n) {
        return (new MailMessage)
            ->subject('🎉 Your Pyonea Seller Account is Approved!')
            ->view('emails.seller-approved', ['seller'=>$this->profile]);
    }
    public function toArray($n): array {
        return ['type'=>'seller_approved','message'=>'Your seller account has been approved. You can now start selling!'];
    }
}