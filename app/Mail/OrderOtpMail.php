<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $otp,
        public string $userName,
        public string $orderTotal
    ) {}

    public function build(): static
    {
        return $this
            ->subject("Your Pyonea Order Confirmation Code: {$this->otp}")
            ->view('emails.order-otp', [
                'otp'        => $this->otp,
                'userName'   => $this->userName,
                'orderTotal' => $this->orderTotal,
            ]);
    }
}
