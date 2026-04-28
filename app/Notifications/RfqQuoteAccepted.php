<?php

namespace App\Notifications;

use App\Models\Rfq;
use App\Models\RfqQuote;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the seller whose quote has been accepted by the buyer.
 */
class RfqQuoteAccepted extends Notification
{
    public function __construct(
        public Rfq      $rfq,
        public RfqQuote $quote,
    ) {}

    public function via($notifiable): array
    {
        $channels = ['database'];
        if (!empty($notifiable->email)) {
            $channels[] = 'mail';
        }
        return $channels;
    }

    public function toMail($notifiable): MailMessage
    {
        $buyerName = $this->rfq->buyer?->name ?? 'The buyer';
        $total     = number_format($this->quote->total_price) . ' ' . $this->quote->currency;

        return (new MailMessage)
            ->subject("🎉 Your Quote Was Accepted — {$this->rfq->rfq_number}")
            ->greeting("Congratulations {$notifiable->name}!")
            ->line("{$buyerName} has accepted your quote on their RFQ.")
            ->line("**RFQ:** {$this->rfq->rfq_number} — {$this->rfq->product_name}")
            ->line("**Your Quoted Price:** {$total}")
            ->line("**Delivery Commitment:** {$this->quote->delivery_days} days")
            ->action('View RFQ Details', config('app.frontend_url') . '/rfq')
            ->line('Please get in touch with the buyer to confirm the order details.');
    }

    public function toArray($notifiable): array
    {
        $buyerName = $this->rfq->buyer?->name ?? 'The buyer';

        return [
            'type'         => 'rfq_quote_accepted',
            'rfq_id'       => $this->rfq->id,
            'rfq_number'   => $this->rfq->rfq_number,
            'quote_id'     => $this->quote->id,
            'buyer_name'   => $buyerName,
            'total_price'  => $this->quote->total_price,
            'currency'     => $this->quote->currency,
            'message'      => "Your quote on RFQ {$this->rfq->rfq_number} ({$this->rfq->product_name}) has been accepted!",
        ];
    }
}