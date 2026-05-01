<?php
// app/Notifications/RfqQuoteAccepted.php

namespace App\Notifications;

use App\Models\Order;
use App\Models\Rfq;
use App\Models\RfqQuote;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the seller whose quote has been accepted by the buyer.
 * Now also carries the Order that was automatically created so the seller
 * can immediately see the order number and start fulfillment.
 */
class RfqQuoteAccepted extends Notification
{
    public function __construct(
        public Rfq      $rfq,
        public RfqQuote $quote,
        public Order    $order,   // ← NEW: the order created on acceptance
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
        $buyerName   = $this->rfq->buyer?->name ?? 'The buyer';
        $total       = number_format($this->quote->total_price) . ' ' . $this->quote->currency;
        $orderNumber = $this->order->order_number;

        return (new MailMessage)
            ->subject("🎉 Your Quote Was Accepted — {$this->rfq->rfq_number} | Order {$orderNumber}")
            ->greeting("Congratulations {$notifiable->name}!")
            ->line("{$buyerName} has accepted your quote and an order has been created automatically.")
            ->line("**RFQ:** {$this->rfq->rfq_number} — {$this->rfq->product_name}")
            ->line("**Your Quoted Price:** {$total}")
            ->line("**Delivery Commitment:** {$this->quote->delivery_days} days")
            ->line("**Order Number:** {$orderNumber}")
            ->action('View Order', config('app.frontend_url') . '/orders/' . $orderNumber)
            ->line('Please confirm the order and coordinate delivery with the buyer.');
    }

    public function toArray($notifiable): array
    {
        $buyerName = $this->rfq->buyer?->name ?? 'The buyer';

        return [
            'type'         => 'rfq_quote_accepted',
            'rfq_id'       => $this->rfq->id,
            'rfq_number'   => $this->rfq->rfq_number,
            'quote_id'     => $this->quote->id,
            'order_id'     => $this->order->id,          // ← NEW
            'order_number' => $this->order->order_number, // ← NEW
            'buyer_name'   => $buyerName,
            'total_price'  => $this->quote->total_price,
            'currency'     => $this->quote->currency,
            'message'      => "Your quote on RFQ {$this->rfq->rfq_number} ({$this->rfq->product_name}) "
                . "was accepted! Order {$this->order->order_number} has been created.",
        ];
    }
}