<?php

namespace App\Notifications\Channels;

use App\Models\PushToken;
use App\Services\ExpoPushService;
use Illuminate\Notifications\Notification;

class ExpoPushChannel
{
    public function __construct(private ExpoPushService $expoPush) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (!method_exists($notification, 'toExpoPush')) {
            return;
        }

        /** @var array<string, mixed> $message */
        $message = $notification->toExpoPush($notifiable);
        if (empty($message['title']) && empty($message['body'])) {
            return;
        }

        $tokens = PushToken::query()
            ->where('user_id', $notifiable->id)
            ->where('provider', 'expo')
            ->get();

        $this->expoPush->sendToTokens($tokens, $message);
    }
}
