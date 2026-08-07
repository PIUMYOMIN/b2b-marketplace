<?php

namespace App\Mail;

use App\Models\EmailCampaign;
use App\Support\MailIdentity;
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
        $mail = MailIdentity::apply($this, 'newsletter')
            ->subject($this->campaign->subject)
            ->view('emails.newsletter', [
                'campaign' => $this->campaign,
                'token'    => $this->token,
            ]);

        if ($this->token) {
            $unsubscribeUrl = config('app.frontend_url') . '/unsubscribe?token=' . urlencode($this->token);

            $mail->withSymfonyMessage(function ($message) use ($unsubscribeUrl): void {
                $headers = $message->getHeaders();
                $headers->addTextHeader('List-Unsubscribe', '<' . $unsubscribeUrl . '>');
                $headers->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
                $headers->addTextHeader('Precedence', 'bulk');
            });
        }

        return $mail;
    }
}