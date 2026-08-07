<?php

namespace App\Mail;

use App\Support\MailIdentity;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewsletterConfirmMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $token,
        public ?string $name
    ) {}

    public function build(): static
    {
        return MailIdentity::apply($this, 'newsletter')
            ->subject('Confirm your Pyonea subscription')
            ->view('emails.newsletter-confirm', [
                'token' => $this->token,
                'name'  => $this->name,
            ]);
    }
}