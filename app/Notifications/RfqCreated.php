<?php

namespace App\Notifications;

use App\Models\Rfq;
use App\Notifications\Concerns\SendsExpoPush;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to sellers when a buyer creates a targeted (non-broadcast) RFQ
 * that explicitly lists them as a recipient.
 */
class RfqCreated extends Notification
{
    use SendsExpoPush;

    public function __construct(public Rfq $rfq) {}

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
        $buyer = $this->rfq->buyer;

        return (new MailMessage)
            ->subject("New RFQ from {$buyer->name} — {$this->rfq->rfq_number}")
            ->greeting("Hello {$notifiable->name},")
            ->line("A buyer has sent you a Request for Quotation.")
            ->line("**Product:** {$this->rfq->product_name}")
            ->line("**Category:** " . ($this->rfq->category ?: 'General'))
            ->line("**Quantity:** {$this->rfq->quantity} {$this->rfq->unit}")
            ->line("**Deadline:** {$this->rfq->deadline->format('d M Y')}")
            ->action('View RFQ & Submit Quote', config('app.frontend_url') . '/rfq')
            ->line('Please respond before the deadline.');
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'rfq_created',
            'rfq_id' => $this->rfq->id,
            'rfq_number' => $this->rfq->rfq_number,
            'product_name' => $this->rfq->product_name,
            'buyer_name' => $this->rfq->buyer->name ?? 'A buyer',
            'deadline' => $this->rfq->deadline->toDateString(),
            'url' => '/seller/dashboard?tab=rfq',
            'message' => "New RFQ {$this->rfq->rfq_number} for \"{$this->rfq->product_name}\" — deadline {$this->rfq->deadline->format('d M Y')}.",
        ];
    }

    public function toExpoPush($notifiable): array
    {
        $body = "New RFQ {$this->rfq->rfq_number}: {$this->rfq->product_name}";

        return $this->expoPushPayload(
            'New RFQ',
            $body,
            'admin',
            [
                'type' => 'rfq_created',
                'rfq_id' => (string) $this->rfq->id,
                'rfq_number' => (string) $this->rfq->rfq_number,
                'product_name' => (string) $this->rfq->product_name,
                'message' => $body,
                'url' => '/seller/dashboard?tab=rfq',
            ],
        );
    }
}
