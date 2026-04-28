<?php

namespace App\Notifications;

use App\Models\Rfq;
use App\Models\RfqQuote;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the buyer when a seller submits (or updates) a quote on their RFQ.
 */
class RfqQuoteReceived extends Notification
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
        $storeName = $this->quote->seller?->sellerProfile?->store_name
            ?? $this->quote->seller?->name
            ?? 'A seller';

        $total = number_format($this->quote->total_price) . ' ' . $this->quote->currency;

        return (new MailMessage)
            ->subject("New Quote on {$this->rfq->rfq_number} — {$this->rfq->product_name}")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$storeName} has submitted a quote on your RFQ.")
            ->line("**RFQ:** {$this->rfq->rfq_number} — {$this->rfq->product_name}")
            ->line("**Total Price:** {$total}")
            ->line("**Delivery:** {$this->quote->delivery_days} days")
            ->line("**Valid Until:** {$this->quote->valid_until->format('d M Y')}")
            ->action('Review Quotes', config('app.frontend_url') . '/rfq')
            ->line('Accept the best quote before the deadline.');
    }

    public function toArray($notifiable): array
    {
        $storeName = $this->quote->seller?->sellerProfile?->store_name
            ?? $this->quote->seller?->name
            ?? 'A seller';

        return [
            'type'         => 'rfq_quote_received',
            'rfq_id'       => $this->rfq->id,
            'rfq_number'   => $this->rfq->rfq_number,
            'quote_id'     => $this->quote->id,
            'seller_name'  => $storeName,
            'total_price'  => $this->quote->total_price,
            'currency'     => $this->quote->currency,
            'message'      => "{$storeName} submitted a quote of " . number_format($this->quote->total_price) . " {$this->quote->currency} on RFQ {$this->rfq->rfq_number}.",
        ];
    }
}