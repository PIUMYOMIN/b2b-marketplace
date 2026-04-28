<?php

namespace App\Notifications;

use App\Models\Rfq;
use App\Models\RfqQuote;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to a seller when their quote is rejected — either explicitly by the buyer
 * or automatically when another quote is accepted.
 *
 * Mail is intentionally skipped for auto-rejections (when another quote is
 * accepted) to avoid spamming every competing seller. The database notification
 * is always written so the seller can see the outcome in their notification feed.
 *
 * Pass $explicit = true only when the buyer manually rejects a single quote.
 */
class RfqQuoteRejected extends Notification
{
    public function __construct(
        public Rfq      $rfq,
        public RfqQuote $quote,
        public bool     $explicit = false,   // true = buyer manually rejected; false = auto-rejected on accept
    ) {}

    public function via($notifiable): array
    {
        $channels = ['database'];
        // Only send mail for explicit rejections — auto-rejections are silent
        if ($this->explicit && !empty($notifiable->email)) {
            $channels[] = 'mail';
        }
        return $channels;
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
            'type'        => 'rfq_quote_rejected',
            'rfq_id'      => $this->rfq->id,
            'rfq_number'  => $this->rfq->rfq_number,
            'quote_id'    => $this->quote->id,
            'explicit'    => $this->explicit,
            'message'     => "Your quote on RFQ {$this->rfq->rfq_number} ({$this->rfq->product_name}) was not selected.",
        ];
    }
}