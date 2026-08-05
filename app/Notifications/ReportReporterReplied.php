<?php

namespace App\Notifications;

use App\Models\Report;
use App\Notifications\Concerns\SendsExpoPush;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReportReporterReplied extends Notification
{
    use SendsExpoPush;

    public function __construct(
        public Report $report,
        public string $commentBody,
    ) {}

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

        return (new MailMessage)
            ->subject("[{$this->report->ticket_id}] Reporter replied — {$this->report->subject}")
            ->greeting('Reporter Replied to Support Ticket')
            ->line("**{$reporterName}** replied to ticket **{$this->report->ticket_id}**.")
            ->line('**Subject:** ' . $this->report->subject)
            ->line('**Reply:** ' . \Illuminate\Support\Str::limit($this->commentBody, 500))
            ->action('Open Ticket', $this->dashboardUrl())
            ->line('Please review the reply in the admin dashboard.');
    }

    public function toArray($notifiable): array
    {
        $reporterName = $this->report->reporter?->name
            ?? $this->report->guest_name
            ?? 'Guest';

        return [
            'type' => 'report_reporter_replied',
            'ticket_id' => $this->report->ticket_id,
            'report_id' => $this->report->id,
            'subject' => $this->report->subject,
            'reporter_name' => $reporterName,
            'url' => $this->dashboardPath(),
            'message' => "[{$this->report->ticket_id}] {$reporterName} replied: " . \Illuminate\Support\Str::limit($this->commentBody, 120),
        ];
    }

    public function toExpoPush($notifiable): array
    {
        $reporterName = $this->report->reporter?->name
            ?? $this->report->guest_name
            ?? 'Guest';
        $body = "{$reporterName} replied to {$this->report->ticket_id}";

        return $this->expoPushPayload(
            "Reporter Replied — {$this->report->ticket_id}",
            $body,
            'admin',
            [
                'type' => 'report_reporter_replied',
                'ticket_id' => $this->report->ticket_id,
                'report_id' => (string) $this->report->id,
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
