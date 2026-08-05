<?php

namespace App\Notifications;

use App\Models\Report;
use App\Notifications\Concerns\SendsExpoPush;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewReportSubmitted extends Notification
{
    use SendsExpoPush;

    public function __construct(public Report $report) {}

    public function via($notifiable): array
    {
        $channels = ['database'];

        if (! empty($notifiable->email)) {
            $channels[] = 'mail';
        }

        $channels = array_merge($channels, $this->mobilePushChannels(true));

        return $channels;
    }

    public function toMail($notifiable): MailMessage
    {
        $reporterName = $this->report->reporter?->name
            ?? $this->report->guest_name
            ?? 'Guest';
        $reporterEmail = $this->report->reporter?->email
            ?? $this->report->guest_email
            ?? 'No email provided';

        return (new MailMessage)
            ->subject("[{$this->report->ticket_id}] New {$this->report->priority} report: {$this->report->subject}")
            ->greeting('New Support Report')
            ->line("A new **{$this->report->priority}** priority report has been submitted.")
            ->line('**Ticket:** ' . $this->report->ticket_id)
            ->line('**Subject:** ' . $this->report->subject)
            ->line('**Category:** ' . ucfirst($this->report->category))
            ->line("**Reporter:** {$reporterName} ({$reporterEmail})")
            ->line('**Description:** ' . \Illuminate\Support\Str::limit($this->report->description, 300))
            ->action('Review Report', $this->dashboardUrl())
            ->line('Please review and respond from the admin dashboard.');
    }

    public function toArray($notifiable): array
    {
        $reporterName = $this->report->reporter?->name
            ?? $this->report->guest_name
            ?? 'Guest';

        return [
            'type' => 'new_report',
            'ticket_id' => $this->report->ticket_id,
            'report_id' => $this->report->id,
            'subject' => $this->report->subject,
            'category' => $this->report->category,
            'priority' => $this->report->priority,
            'reporter_name' => $reporterName,
            'url' => $this->dashboardPath(),
            'message' => "[{$this->report->ticket_id}] New {$this->report->priority} report from {$reporterName}: {$this->report->subject}",
        ];
    }

    public function toExpoPush($notifiable): array
    {
        $reporterName = $this->report->reporter?->name
            ?? $this->report->guest_name
            ?? 'Guest';
        $body = "{$reporterName} submitted a {$this->report->priority} priority report: {$this->report->subject}";

        return $this->expoPushPayload(
            "New Report — {$this->report->ticket_id}",
            $body,
            'admin',
            [
                'type' => 'new_report',
                'ticket_id' => $this->report->ticket_id,
                'report_id' => (string) $this->report->id,
                'priority' => $this->report->priority,
                'url' => $this->dashboardPath(),
                'message' => $body,
            ],
        );
    }

    private function dashboardPath(): string
    {
        return '/admin/dashboard?tab=reports&ticket=' . rawurlencode($this->report->ticket_id);
    }

    private function dashboardUrl(): string
    {
        return rtrim((string) config('app.frontend_url'), '/') . $this->dashboardPath();
    }
}
