<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Concerns\SendsExpoPush;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewUserRegistered extends Notification
{
    use SendsExpoPush;

    public function __construct(public User $newUser) {}

    public function via($notifiable): array
    {
        $channels = ['database'];

        if (! empty($notifiable->email)) {
            $channels[] = 'mail';
        }

        // Native Expo push for admins logged into the app
        $channels = array_merge($channels, $this->mobilePushChannels(true));

        return $channels;
    }

    public function toMail($notifiable): MailMessage
    {
        $dashboard = rtrim(config('app.frontend_url'), '/') . '/admin/dashboard';
        $userType  = ucfirst($this->newUser->type ?? 'User');

        return (new MailMessage)
            ->subject("New {$userType} Registered — {$this->newUser->name}")
            ->greeting("New {$userType} Registration")
            ->line("**{$this->newUser->name}** has just registered as a **{$userType}**.")
            ->line('**Email:** ' . ($this->newUser->email ?? 'Not provided'))
            ->line('**Phone:** ' . ($this->newUser->phone ?? 'Not provided'))
            ->line('**Registered:** ' . $this->newUser->created_at->format('d M Y H:i'))
            ->action('View in Admin Dashboard', $dashboard)
            ->line('No action is required unless the account needs review.');
    }

    public function toArray($notifiable): array
    {
        $userType = $this->newUser->type ?? 'user';
        $message = "New {$userType} registered: {$this->newUser->name}";

        return [
            'type'      => 'new_user_registered',
            'user_id'   => $this->newUser->id,
            'user_name' => $this->newUser->name,
            'user_type' => $userType,
            'url'       => '/admin/dashboard?tab=users',
            'message'   => $message,
        ];
    }

    public function toExpoPush($notifiable): array
    {
        $userType = ucfirst($this->newUser->type ?? 'User');
        $body = "{$this->newUser->name} registered as a {$userType}.";

        return $this->expoPushPayload(
            "New {$userType} Registered",
            $body,
            'admin',
            [
                'type' => 'new_user_registered',
                'user_id' => (string) $this->newUser->id,
                'user_name' => $this->newUser->name,
                'user_type' => $this->newUser->type,
                'url' => '/admin/dashboard?tab=users',
                'message' => $body,
            ],
        );
    }
}
