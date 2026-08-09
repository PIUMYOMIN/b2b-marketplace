<?php

namespace App\Notifications;

use App\Models\Rfq;
use App\Models\RfqQuote;
use App\Notifications\Concerns\SendsExpoPush;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to a seller when their quote is rejected.
 * Push + mail only for explicit buyer rejections (not auto-reject on accept).
 */
class RfqQuoteRejected extends Notification
{
    use SendsExpoPush;

    public function __construct(
        public Rfq $rfq,
        public RfqQuote $quote,
        public bool $explicit = false,
    ) {}

    public function via($notifiable): array
    {
        $channels = ['database'];
        if ($this->explicit && !empty($notifiable->email)) {
            $channels[] = 'mail';
        }

        return array_merge(
            $channels,
            $this->mobilePushChannels($this->explicit && $this->pushNotificationsEnabled($notifiable)),
        );
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Update on RFQ {$this->rfq->rfq_number}")
            ->greeting("Hello {$notifiable->name},")
            ->line("The buyer has reviewed all quotes for the following RFQ and has chosen a different supplier.")
            ->line("**RFQ:** {$this->rfq->rfq_number} — {$this->rfq->product_name}")
            ->action('Browse Open RFQs', config('app.frontend_url') . '/rfq')
            ->line('Thank you for participating. Other RFQs may be a better fit for you.');
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'rfq_quote_rejected',
            'rfq_id' => $this->rfq->id,
            'rfq_number' => $this->rfq->rfq_number,
            'quote_id' => $this->quote->id,
            'explicit' => $this->explicit,
            'url' => '/seller/dashboard?tab=rfq',
            'message' => "Your quote on RFQ {$this->rfq->rfq_number} ({$this->rfq->product_name}) was not selected.",
        ];
    }

    public function toExpoPush($notifiable): array
    {
        $body = "Your quote on RFQ {$this->rfq->rfq_number} was not selected.";

        return $this->expoPushPayload(
            'RFQ Quote Update',
            $body,
            'admin',
            [
                'type' => 'rfq_quote_rejected',
                'rfq_id' => (string) $this->rfq->id,
                'rfq_number' => (string) $this->rfq->rfq_number,
                'quote_id' => (string) $this->quote->id,
                'message' => $body,
                'url' => '/seller/dashboard?tab=rfq',
            ],
        );
    }
}
