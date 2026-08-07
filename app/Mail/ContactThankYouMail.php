<?php

namespace App\Mail;

use App\Support\MailIdentity;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContactThankYouMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public array $data) {}

    public function build(): static
    {
        return MailIdentity::apply($this, 'contact')
            ->subject('We received your message — Pyonea')
            ->view('emails.contact-thank-you', [
                'data' => $this->data,
            ]);
    }
}
