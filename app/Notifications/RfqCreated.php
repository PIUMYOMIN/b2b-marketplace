<?php

namespace App\Notifications;

use App\Models\Rfq;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to sellers when a buyer creates a targeted (non-broadcast) RFQ
 * that explicitly lists them as a recipient.
 *
 * Broadcast RFQs are visible to all sellers via the "Received" tab without
 * spamming every seller's inbox — so we skip mail for those and rely on
 * the sellers discovering them on the platform.
 */
class RfqCreated extends Notification
{
    public function __construct(public Rfq $rfq) {}

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
        $buyer = $this->rfq->buyer;

        return (new MailMessage)
            ->subject("New RFQ from {$buyer->name} — {$this->rfq->rfq_number}")
            ->greeting("Hello {$notifiable->name},")
            ->line("A buyer has sent you a Request for Quotation.")
            ->line("**Product:** {$this->rfq->product_name}")
            ->line("**Quantity:** {$this->rfq->quantity} {$this->rfq->unit}")
            ->line("**Deadline:** {$this->rfq->deadline->format('d M Y')}")
            ->action('View RFQ & Submit Quote', config('app.frontend_url') . '/rfq')
            ->line('Please respond before the deadline.');
    }

    public function toArray($notifiable): array
    {
        return [
            'type'         => 'rfq_created',
            'rfq_id'       => $this->rfq->id,
            'rfq_number'   => $this->rfq->rfq_number,
            'product_name' => $this->rfq->product_name,
            'buyer_name'   => $this->rfq->buyer->name ?? 'A buyer',
            'deadline'     => $this->rfq->deadline->toDateString(),
            'message'      => "New RFQ {$this->rfq->rfq_number} for \"{$this->rfq->product_name}\" — deadline {$this->rfq->deadline->format('d M Y')}.",
        ];
    }
}