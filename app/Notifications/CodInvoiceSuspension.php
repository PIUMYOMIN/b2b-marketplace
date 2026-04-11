<?php
// app/Notifications/CodInvoiceSuspension.php

namespace App\Notifications;

use App\Models\CodCommissionInvoice;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CodInvoiceSuspension extends Notification
{
    public function __construct(public CodCommissionInvoice $invoice) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("🚫 Your Listings Have Been Suspended — Pyonea")
            ->greeting("Hi {$notifiable->name},")
            ->line("Due to the overdue COD commission invoice **{$this->invoice->invoice_number}**, your product listings have been temporarily suspended.")
            ->line("Amount due: **" . number_format($this->invoice->commission_amount) . " MMK**")
            ->line("Your listings will be **automatically restored** once payment is confirmed by our team.")
            ->action('Pay & Restore Listings', config('app.frontend_url') . '/seller/wallet')
            ->line('Reply to this email or contact support to expedite restoration.');
    }

    public function toArray($notifiable): array
    {
        return [
            'type'           => 'cod_invoice_suspension',
            'invoice_id'     => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'amount'         => $this->invoice->commission_amount,
            'message'        => "Your listings have been suspended due to overdue COD invoice {$this->invoice->invoice_number}.",
        ];
    }
}