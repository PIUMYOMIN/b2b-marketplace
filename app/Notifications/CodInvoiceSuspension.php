<?php

namespace App\Notifications;

use App\Models\CodCommissionInvoice;
use App\Notifications\Concerns\SendsExpoPush;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CodInvoiceSuspension extends Notification
{
    use SendsExpoPush;

    public function __construct(public CodCommissionInvoice $invoice) {}

    public function via($notifiable): array
    {
        $channels = ['mail', 'database'];

        return array_merge($channels, $this->mobilePushChannels($this->pushNotificationsEnabled($notifiable)));
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Listings Have Been Suspended — Pyonea')
            ->greeting("Hi {$notifiable->name},")
            ->line("Due to the overdue COD commission invoice **{$this->invoice->invoice_number}**, your product listings have been temporarily suspended.")
            ->line('Amount due: **' . number_format($this->invoice->commission_amount) . ' MMK**')
            ->line('Your listings will be **automatically restored** once payment is confirmed by our team.')
            ->action('Pay & Restore Listings', config('app.frontend_url') . '/seller/wallet')
            ->line('Reply to this email or contact support to expedite restoration.');
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'cod_invoice_suspension',
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'amount' => $this->invoice->commission_amount,
            'url' => '/seller/dashboard?tab=wallet',
            'message' => "Your listings have been suspended due to overdue COD invoice {$this->invoice->invoice_number}.",
        ];
    }

    public function toExpoPush($notifiable): array
    {
        $body = "Listings suspended — overdue COD invoice {$this->invoice->invoice_number}.";

        return $this->expoPushPayload(
            'Listings Suspended',
            $body,
            'admin',
            [
                'type' => 'cod_invoice_suspension',
                'invoice_id' => (string) $this->invoice->id,
                'invoice_number' => (string) $this->invoice->invoice_number,
                'message' => $body,
                'url' => '/seller/dashboard?tab=wallet',
            ],
        );
    }
}
