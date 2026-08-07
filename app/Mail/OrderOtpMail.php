<?php

namespace App\Mail;

use App\Support\MailIdentity;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $otp,
        public string $userName,
        public string $orderTotal,
        public ?string $userEmail = null,
    ) {}

    public function build(): static
    {
        return MailIdentity::apply($this, 'transactional')
            ->subject('Your Pyonea checkout verification code')
            ->view('emails.order-otp', [
                'otp'        => $this->otp,
                'userName'   => $this->userName,
                'orderTotal' => $this->orderTotal,
                'userEmail'  => $this->userEmail,
            ]);
    }
}
