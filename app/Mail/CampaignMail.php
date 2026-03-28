<?php

namespace App\Mail;

use App\Models\EmailCampaign;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CampaignMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public EmailCampaign $campaign,
        public ?string $token = null
    ) {}

    public function build(): static
    {
        return $this
            ->subject($this->campaign->subject)
            ->view('emails.newsletter', [
                'campaign' => $this->campaign,
                'token'    => $this->token,
            ]);
    }
}