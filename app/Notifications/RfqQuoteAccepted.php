<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\Rfq;
use App\Models\RfqQuote;
use App\Notifications\Concerns\SendsExpoPush;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the seller whose quote has been accepted by the buyer.
 */
class RfqQuoteAccepted extends Notification
{
    use SendsExpoPush;

    public function __construct(
        public Rfq $rfq,
        public RfqQuote $quote,
        public Order $order,
    ) {}

    public function via($notifiable): array
    {
        $channels = ['database'];
        if (!empty($notifiable->email)) {
            $channels[] = 'mail';
        }

        return array_merge($channels, $this->mobilePushChannels($this->pushNotificationsEnabled($notifiable)));
    }

    public function toMail($notifiable): MailMessage
    {
        $buyerName = $this->rfq->buyer?->name ?? 'The buyer';
        $total = number_format($this->quote->total_price) . ' ' . $this->quote->currency;
        $orderNumber = $this->order->order_number;

        return (new MailMessage)
            ->subject("Your Quote Was Accepted — {$this->rfq->rfq_number} | Order {$orderNumber}")
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
            'type' => 'rfq_quote_accepted',
            'rfq_id' => $this->rfq->id,
            'rfq_number' => $this->rfq->rfq_number,
            'quote_id' => $this->quote->id,
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'buyer_name' => $buyerName,
            'total_price' => $this->quote->total_price,
            'currency' => $this->quote->currency,
            'url' => '/seller/dashboard?tab=orders',
            'message' => "Your quote on RFQ {$this->rfq->rfq_number} ({$this->rfq->product_name}) "
                . "was accepted! Order {$this->order->order_number} has been created.",
        ];
    }

    public function toExpoPush($notifiable): array
    {
        $body = "Quote accepted — order {$this->order->order_number} created.";

        return $this->expoPushPayload(
            'RFQ Quote Accepted',
            $body,
            'orders',
            [
                'type' => 'rfq_quote_accepted',
                'rfq_id' => (string) $this->rfq->id,
                'rfq_number' => (string) $this->rfq->rfq_number,
                'quote_id' => (string) $this->quote->id,
                'order_id' => (string) $this->order->id,
                'order_number' => (string) $this->order->order_number,
                'message' => $body,
                'url' => '/seller/dashboard?tab=orders',
            ],
        );
    }
}
