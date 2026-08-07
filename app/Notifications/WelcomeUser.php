<?php

namespace App\Notifications;

use App\Notifications\Concerns\SendsExpoPush;
use App\Support\MailIdentity;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeUser extends Notification
{
    use Queueable;
    use SendsExpoPush;

    public function via(object $notifiable): array
    {
        $channels = ['mail', 'database'];

        $channels = array_merge($channels, $this->mobilePushChannels($this->shouldSendWelcomePush($notifiable)));

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return MailIdentity::applyToMailMessage(
            (new MailMessage)
            ->subject("Welcome to Pyonea, {$notifiable->name}!")
            ->view('emails.welcome', ['user' => $notifiable]),
            'welcome'
        );
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'welcome',
            'message' => "Welcome to Pyonea, {$notifiable->name}!",
        ];
    }

    /** @return array<string, mixed> */
    public function toExpoPush(object $notifiable): array
    {
        $body = "Welcome to Pyonea, {$notifiable->name}! Start exploring products and sellers.";

        return $this->expoPushPayload(
            'Welcome to Pyonea',
            $body,
            'default',
            [
                'type' => 'welcome',
                'url' => '/',
                'message' => $body,
            ],
        );
    }

    private function shouldSendWelcomePush(object $user): bool
    {
        $prefs = $this->notificationPreferences($user);

        return (bool) ($prefs['welcome_notifications'] ?? $prefs['marketing_notifications'] ?? true);
    }
}
