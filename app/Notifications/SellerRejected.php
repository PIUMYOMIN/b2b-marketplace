<?php
namespace App\Notifications;
use App\Models\SellerProfile;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SellerRejected extends Notification {
    use Queueable;
    public function __construct(public SellerProfile $profile, public ?string $reason = null) {}
    public function via($n): array { return ['mail','database']; }
    public function toMail($n) {
        return (new MailMessage)
            ->subject('Pyonea Seller Application Update')
            ->view('emails.seller-rejected', ['seller'=>$this->profile,'reason'=>$this->reason]);
    }
    public function toArray($n): array {
        return ['type'=>'seller_rejected','reason'=>$this->reason,'message'=>'Your seller application requires attention. Please check your email for details.'];
    }
}