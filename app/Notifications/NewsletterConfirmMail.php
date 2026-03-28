<?php
namespace App\Mail;
use App\Models\NewsletterSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewsletterConfirmMail extends Mailable {
    use Queueable, SerializesModels;
    public function __construct(public NewsletterSubscriber $subscriber) {}
    public function build() {
        return $this->subject('Confirm your Pyonea subscription')
            ->view('emails.newsletter-confirm', [
                'token' => $this->subscriber->confirm_token,
                'name'  => $this->subscriber->name,
            ]);
    }
}
