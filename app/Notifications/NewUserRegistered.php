<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class NewUserRegistered extends Notification
{
    public function __construct(public User $newUser) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
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
        return [
            'type'      => 'new_user_registered',
            'user_id'   => $this->newUser->id,
            'user_name' => $this->newUser->name,
            'user_type' => $this->newUser->type,
            'message'   => "New {$this->newUser->type} registered: {$this->newUser->name}",
        ];
    }
}