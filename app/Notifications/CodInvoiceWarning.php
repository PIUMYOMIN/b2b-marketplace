<?php

namespace App\Notifications;

use App\Models\CodCommissionInvoice;
use App\Notifications\Concerns\SendsExpoPush;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CodInvoiceWarning extends Notification
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
        $daysOver = $this->invoice->days_overdue;

        return (new MailMessage)
            ->subject("COD Commission Overdue — {$this->invoice->invoice_number}")
            ->greeting("Hi {$notifiable->name},")
            ->line("Your COD commission invoice **{$this->invoice->invoice_number}** is {$daysOver} days overdue.")
            ->line('Amount due: **' . number_format($this->invoice->commission_amount) . ' MMK**')
            ->line('**If not paid within 3 days, your product listings will be suspended.**')
            ->action('Pay Now', config('app.frontend_url') . '/seller/wallet')
            ->line('Please contact support if you have any questions.');
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'cod_invoice_warning',
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'amount' => $this->invoice->commission_amount,
            'days_overdue' => $this->invoice->days_overdue,
            'url' => '/seller/dashboard?tab=wallet',
            'message' => "COD invoice {$this->invoice->invoice_number} is overdue. Please pay to avoid suspension.",
        ];
    }

    public function toExpoPush($notifiable): array
    {
        $body = "COD invoice {$this->invoice->invoice_number} is overdue. Pay to avoid suspension.";

        return $this->expoPushPayload(
            'COD Invoice Overdue',
            $body,
            'admin',
            [
                'type' => 'cod_invoice_warning',
                'invoice_id' => (string) $this->invoice->id,
                'invoice_number' => (string) $this->invoice->invoice_number,
                'message' => $body,
                'url' => '/seller/dashboard?tab=wallet',
            ],
        );
    }
}
